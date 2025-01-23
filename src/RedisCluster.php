<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception;
use Laminas\Cache\Exception\RuntimeException;
use Laminas\Cache\Storage\AbstractMetadataCapableAdapter;
use Laminas\Cache\Storage\Adapter\Exception\MetadataErrorException;
use Laminas\Cache\Storage\Adapter\Exception\RedisRuntimeException;
use Laminas\Cache\Storage\Adapter\Redis\Metadata;
use Laminas\Cache\Storage\Capabilities;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\ClearByPrefixInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Redis as RedisFromExtension;
use RedisCluster as RedisClusterFromExtension;
use RedisClusterException;
use RedisException;

use function array_key_exists;
use function assert;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function version_compare;

/**
 * @template-extends AbstractMetadataCapableAdapter<RedisClusterOptions,Metadata>
 * @psalm-import-type SupportedDataTypesArrayShape from Capabilities
 */
final class RedisCluster extends AbstractMetadataCapableAdapter implements
    ClearByNamespaceInterface,
    ClearByPrefixInterface,
    FlushableInterface
{
    private RedisClusterFromExtension|null $resource;

    private string|null $namespacePrefix;

    private RedisClusterResourceManagerInterface|null $resourceManager;

    /**
     * @param null|iterable<string,mixed>|RedisClusterOptions $options
     */
    public function __construct($options = null)
    {
        $this->resourceManager = null;
        $this->resource        = null;
        $this->namespacePrefix = null;
        parent::__construct($options);

        $eventManager = $this->getEventManager();
        $eventManager->attach('option', function (): void {
            $this->resource        = null;
            $this->capabilities    = null;
            $this->namespacePrefix = null;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(iterable|AdapterOptions $options): self
    {
        if (! $options instanceof RedisClusterOptions) {
            $options = new RedisClusterOptions($options);
        }

        parent::setOptions($options);
        return $this;
    }

    /**
     * {@inheritDoc}
     * In RedisCluster, it is totally okay if just one primary server is being flushed.
     * If one or more primaries are not reachable, they will re-sync if they're coming back online.
     *
     * One has to connect to the primaries directly using {@see RedisFromExtension::connect}.
     */
    public function flush(): bool
    {
        $resource                     = $this->getRedisResource();
        $anyMasterSuccessfullyFlushed = false;
        /** @psalm-var array<array-key,array{0:string,1:int}> $masters */
        $masters = $resource->_masters();

        foreach ($masters as [$host, $port]) {
            $redis = new RedisFromExtension();
            try {
                $redis->connect($host, $port);
            } catch (RedisException $exception) {
                continue;
            }

            if (! $redis->flushAll()) {
                continue;
            }

            $anyMasterSuccessfullyFlushed = true;
        }

        return $anyMasterSuccessfullyFlushed;
    }

    private function getRedisResource(): RedisClusterFromExtension
    {
        if ($this->resource instanceof RedisClusterFromExtension) {
            return $this->resource;
        }

        $resourceManager = $this->getResourceManager();

        try {
            return $this->resource = $resourceManager->getResource();
        } catch (RedisClusterException $exception) {
            throw RedisRuntimeException::fromFailedConnection($exception);
        }
    }

    public function getOptions(): RedisClusterOptions
    {
        $options = parent::getOptions();
        if (! $options instanceof RedisClusterOptions) {
            $options       = new RedisClusterOptions($options);
            $this->options = $options;
        }

        return $options;
    }

    /**
     * {@inheritDoc}
     */
    public function clearByNamespace(string $namespace): bool
    {
        /** @psalm-suppress TypeDoesNotContainType Psalm type does not prevent from injecting empty string */
        if ($namespace === '') {
            throw new Exception\InvalidArgumentException('Invalid namespace provided');
        }

        return $this->searchAndDelete('', $namespace);
    }

    /**
     * {@inheritDoc}
     */
    public function clearByPrefix(string $prefix): bool
    {
        /** @psalm-suppress TypeDoesNotContainType Psalm type does not prevent from injecting empty string */
        if ($prefix === '') {
            throw new Exception\InvalidArgumentException('No prefix given');
        }

        $options = $this->getOptions();

        return $this->searchAndDelete($prefix, $options->getNamespace());
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetItem(
        string $normalizedKey,
        bool|null &$success = null,
        mixed &$casToken = null
    ): mixed {
        $normalizedKeys = [$normalizedKey];
        $values         = $this->internalGetItems($normalizedKeys);
        if (! array_key_exists($normalizedKey, $values)) {
            $success = false;
            return null;
        }

        $value   = $casToken = $values[$normalizedKey];
        $success = true;
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetItems(array $normalizedKeys): array
    {
        $namespacedKeys = [];
        foreach ($normalizedKeys as $normalizedKey) {
            $namespacedKeys[] = $this->createNamespacedKey((string) $normalizedKey);
        }

        $redis = $this->getRedisResource();

        try {
            $resultsByIndex = $redis->mget($namespacedKeys);
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }

        /**
         * @link https://github.com/phpredis/phpredis/blob/35a7cc094c6c264aa37738b074c4c54c4ca73b87/redis_cluster.stub.php#L621
         *
         * @psalm-suppress TypeDoesNotContainType RedisCluster#mget can return `false` on error
         */
        if (! is_array($resultsByIndex)) {
            throw RedisRuntimeException::fromInternalRedisError($redis);
        }

        $result = [];
        foreach ($resultsByIndex as $keyIndex => $value) {
            assert(is_int($keyIndex));
            $normalizedKey = $normalizedKeys[$keyIndex];
            $namespacedKey = $namespacedKeys[$keyIndex];
            if ($value === false && ! $this->isFalseReturnValuePersisted($redis, $namespacedKey)) {
                continue;
            }

            $result[$normalizedKey] = $value;
        }

        return $result;
    }

    /**
     * @param non-empty-string|int $key
     * @return non-empty-string
     */
    private function createNamespacedKey(string|int $key): string
    {
        if ($this->namespacePrefix !== null) {
            return $this->namespacePrefix . $key;
        }

        $options               = $this->getOptions();
        $namespace             = $options->getNamespace();
        $this->namespacePrefix = $namespace;
        if ($namespace !== '') {
            $this->namespacePrefix = $namespace . $options->getNamespaceSeparator();
        }

        return $this->namespacePrefix . $key;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItem(string $normalizedKey, mixed $value): bool
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = (int) $options->getTtl();

        $namespacedKey = $this->createNamespacedKey($normalizedKey);
        try {
            if ($ttl) {
                /**
                 * @psalm-suppress MixedArgument
                 * Redis & RedisCluster do allow mixed values when a serializer is configured.
                 */
                return $redis->setex($namespacedKey, $ttl, $value);
            }

            /**
             * @psalm-suppress MixedArgument
             * Redis & RedisCluster do allow mixed values when a serializer is configured.
             */
            return $redis->set($namespacedKey, $value);
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalRemoveItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();

        try {
            return $redis->del($this->createNamespacedKey($normalizedKey)) === 1;
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalHasItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();

        try {
            /** @psalm-var 0|1 $exists */
            $exists = $redis->exists($this->createNamespacedKey($normalizedKey));
            return (bool) $exists;
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItems(array $normalizedKeyValuePairs): array
    {
        $redis = $this->getRedisResource();
        $ttl   = (int) $this->getOptions()->getTtl();

        $namespacedKeyValuePairs = [];
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            $namespacedKeyValuePairs[$this->createNamespacedKey((string) $normalizedKey)] = $value;
        }

        $successByKey = [];

        try {
            foreach ($namespacedKeyValuePairs as $key => $value) {
                if ($ttl) {
                    /**
                     * @psalm-suppress MixedArgument
                     * Redis & RedisCluster do allow mixed values when a serializer is configured.
                     */
                    $successByKey[$key] = $redis->setex($key, $ttl, $value);
                    continue;
                }

                /**
                 * @psalm-suppress MixedArgument
                 * Redis & RedisCluster do allow mixed values when a serializer is configured.
                 */
                $successByKey[$key] = $redis->set($key, $value);
            }
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }

        $statuses = [];
        foreach ($successByKey as $key => $success) {
            if ($success) {
                continue;
            }

            $statuses[] = $key;
        }

        return $statuses;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetCapabilities(): Capabilities
    {
        if ($this->capabilities !== null) {
            return $this->capabilities;
        }

        $redisVersion           = $this->getRedisVersion();
        $serializer             = $this->hasSerializationSupport();
        $redisVersionLessThanV2 = version_compare($redisVersion, '2.0', '<');
        $redisVersionLessThanV3 = version_compare($redisVersion, '3.0', '<');

        $this->capabilities = new Capabilities(
            maxKeyLength: $redisVersionLessThanV3 ? 255 : 512_000_000,
            ttlSupported: ! $redisVersionLessThanV2,
            namespaceIsPrefix: true,
            supportedDataTypes: $this->getSupportedDatatypes($serializer),
            ttlPrecision: 1,
            usesRequestTime: false,
        );

        return $this->capabilities;
    }

    /**
     * @return SupportedDataTypesArrayShape
     */
    private function getSupportedDatatypes(bool $serializer): array
    {
        if ($serializer) {
            return [
                'NULL'     => true,
                'boolean'  => true,
                'integer'  => true,
                'double'   => true,
                'string'   => true,
                'array'    => 'array',
                'object'   => 'object',
                'resource' => false,
            ];
        }

        return [
            'NULL'     => 'string',
            'boolean'  => 'string',
            'integer'  => 'string',
            'double'   => 'string',
            'string'   => true,
            'array'    => false,
            'object'   => false,
            'resource' => false,
        ];
    }

    private function searchAndDelete(string $prefix, string $namespace): bool
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();

        $prefix = $namespace === '' ? $prefix : $namespace . $options->getNamespaceSeparator() . $prefix;

        /** @var array<array-key,string> $keys */
        $keys = $redis->keys($prefix . '*');
        if (! $keys) {
            return true;
        }

        return $redis->del($keys) === count($keys);
    }

    private function clusterException(
        RedisClusterException $exception,
        RedisClusterFromExtension $redis
    ): Exception\RuntimeException {
        return RedisRuntimeException::fromClusterException($exception, $redis);
    }

    /**
     * This method verifies that the return value from {@see RedisClusterFromExtension::get} or
     * {@see RedisClusterFromExtension::mget} is `false` because the key does not exist or because the keys value
     * is `false` at type-level.
     */
    private function isFalseReturnValuePersisted(RedisClusterFromExtension $redis, string $key): bool
    {
        $serializer = $this
            ->getOptions()
            ->getLibOption(RedisFromExtension::OPT_SERIALIZER, RedisFromExtension::SERIALIZER_NONE);

        if ($serializer === RedisFromExtension::SERIALIZER_NONE) {
            return false;
        }

        try {
            /** @psalm-var 0|1 $exists */
            $exists = $redis->exists($key);
            return (bool) $exists;
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetMetadata(string $normalizedKey): Metadata|null
    {
        $namespacedKey = $this->createNamespacedKey($normalizedKey);
        $redis         = $this->getRedisResource();
        try {
            $ttl = $this->detectTtlForKey($redis, $namespacedKey);
            return new Metadata(remainingTimeToLive: $ttl);
        } catch (MetadataErrorException) {
        } catch (RedisClusterException $exception) {
            throw $this->clusterException($exception, $redis);
        }

        return null;
    }

    /**
     * @param non-empty-string $namespacedKey
     * @return int<-1,max>|null
     */
    private function detectTtlForKey(RedisClusterFromExtension $redis, string $namespacedKey): ?int
    {
        $redisVersion = $this->getRedisVersion();
        $ttl          = $redis->ttl($namespacedKey);

        if (version_compare($redisVersion, '2.8', '>=')) {
            // redis >= 2.8
            // The command 'ttl' returns -2 if the item does not exist
            // and -1 if the item has no associated expire

            if ($ttl <= -2) {
                throw new MetadataErrorException();
            }

            if ($ttl === -1) {
                return Metadata::TTL_UNLIMITED;
            }

            return $ttl;
        }

        if (version_compare($redisVersion, '2.6', '>=')) {
            // redis >= 2.6, < 2.8
            // The command 'ttl' returns -1 if the item does not exist or the item has no associated expire
            if ($ttl <= -1) {
                if (! $this->internalHasItem($namespacedKey)) {
                    throw new MetadataErrorException();
                }

                return Metadata::TTL_UNLIMITED;
            }

            return $ttl;
        }

        // redis >= 2, < 2.6
        // The command 'ttl' returns 0 if the item does not exist same as if the item is going to be expired
        // NOTE: In case of ttl=0 we return false because the item is going to be expired in a very near future
        //       and then doesn't exist any more
        if (version_compare($redisVersion, '2', '>=')) {
            if ($ttl <= -1) {
                if (! $this->internalHasItem($namespacedKey)) {
                    throw new MetadataErrorException();
                }

                return Metadata::TTL_UNLIMITED;
            }

            return $ttl;
        }

        return null;
    }

    public function getRedisVersion(): string
    {
        $options            = $this->getOptions();
        $versionFromOptions = $options->getRedisVersion();
        if ($versionFromOptions) {
            return $versionFromOptions;
        }

        $redisCluster = $this->getRedisResource();
        try {
            $info = $this->info($redisCluster);
        } catch (RedisClusterException $exception) {
            throw RedisRuntimeException::fromClusterException($exception, $redisCluster);
        }

        if (! isset($info['redis_version']) || ! is_string($info['redis_version'])) {
            return '0.0.0-unknown';
        }

        $version = $info['redis_version'];
        assert($version !== '');
        $options->setRedisVersion($version);

        return $version;
    }

    private function hasSerializationSupport(): bool
    {
        $resourceManager = $this->getResourceManager();
        return $resourceManager->hasSerializationSupport($this);
    }

    private function getResourceManager(): RedisClusterResourceManagerInterface
    {
        if ($this->resourceManager !== null) {
            return $this->resourceManager;
        }

        return $this->resourceManager = new RedisClusterResourceManager($this->getOptions());
    }

    public function setResourceManager(RedisClusterResourceManagerInterface $resourceManager): void
    {
        $this->resourceManager = $resourceManager;
    }

    private function info(RedisClusterFromExtension $resource): array
    {
        $options = $this->getOptions();
        if ($options->hasName()) {
            $name = $options->getName();

            return $resource->info($name);
        }

        $seeds = $options->getSeeds();
        if ($seeds === []) {
            throw new RuntimeException('Neither the node name nor any seed is configured.');
        }

        $seed = $seeds[0];
        return $resource->info($seed);
    }
}
