<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception;
use Laminas\Cache\Storage\AbstractMetadataCapableAdapter;
use Laminas\Cache\Storage\Adapter\Exception\RedisRuntimeException;
use Laminas\Cache\Storage\Adapter\Redis\Metadata;
use Laminas\Cache\Storage\Capabilities;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\ClearByPrefixInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\TotalSpaceCapableInterface;
use Redis as RedisFromExtension;
use RedisException as RedisFromExtensionException;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function assert;
use function is_array;
use function is_string;
use function round;
use function version_compare;

/**
 * @template-extends AbstractMetadataCapableAdapter<RedisOptions, Metadata>
 */
final class Redis extends AbstractMetadataCapableAdapter implements
    ClearByNamespaceInterface,
    ClearByPrefixInterface,
    FlushableInterface,
    TotalSpaceCapableInterface
{
    private RedisFromExtension|null $resource;

    private RedisResourceManagerInterface|null $resourceManager;

    /**
     * The namespace prefix
     */
    private string|null $namespacePrefix;

    /**
     * @param null|iterable<string,mixed>|RedisOptions $options
     */
    public function __construct(iterable|RedisOptions|null $options = null)
    {
        parent::__construct($options);
        $this->resourceManager = null;
        $this->resource        = null;
        $this->namespacePrefix = null;
        $this->getEventManager()->attach('option', function (): void {
            $this->resource        = null;
            $this->namespacePrefix = null;
        });
    }

    private function getRedisResource(): RedisFromExtension
    {
        if ($this->resource !== null) {
            return $this->resource;
        }

        $resourceManager = $this->getResourceManager();
        $this->resource  = $resourceManager->getResource();
        $options         = $this->getOptions();

        // init namespace prefix
        $namespace             = $options->getNamespace();
        $this->namespacePrefix = '';
        if ($namespace !== '') {
            $this->namespacePrefix = $namespace . $options->getNamespaceSeparator();
        }

        return $this->resource;
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(iterable|AdapterOptions $options): self
    {
        if (! $options instanceof RedisOptions) {
            $options = new RedisOptions($options);
        }

        parent::setOptions($options);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): RedisOptions
    {
        if (! $this->options) {
            $this->setOptions(new RedisOptions());
        }
        assert($this->options instanceof RedisOptions);
        return $this->options;
    }

    /**
     * @psalm-api
     * @return non-empty-string|null
     */
    public function getPersistentId(bool $update = false): ?string
    {
        $options = $this->getOptions();

        if (! $options->isPersistent()) {
            return null;
        }

        if ($update === false) {
            $persistentId = $options->getPersistentId();
            if ($persistentId !== null) {
                return $persistentId;
            }
        }

        $persistentId = $this->getRedisResource()->getPersistentID();
        if (! is_string($persistentId) || $persistentId === '') {
            return null;
        }

        return $persistentId;
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
            $namespacedKeys[] = $this->createNamespacedKey($normalizedKey);
        }

        $redis = $this->getRedisResource();

        try {
            $resultsByIndex = $redis->mget($namespacedKeys);
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException(
                $exception,
                $redis
            );
        }

        if (! is_array($resultsByIndex)) {
            throw RedisRuntimeException::fromInternalRedisError($redis);
        }

        $result = [];
        foreach ($resultsByIndex as $keyIndex => $value) {
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
     * {@inheritDoc}
     */
    protected function internalHasItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();
        try {
            return (bool) $redis->exists($this->createNamespacedKey($normalizedKey));
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItem(string $normalizedKey, mixed $value): bool
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        try {
            if ($ttl) {
                if ($this->getCapabilities()->ttlSupported === false) {
                    throw new Exception\UnsupportedMethodCallException(
                        'To use ttl you need redis-server version >= 2.0.0',
                    );
                }
                $success = $redis->setex(
                    $this->createNamespacedKey($normalizedKey),
                    (int) $ttl,
                    $this->preSerialize($value)
                ) !== false;
            } else {
                $success = $redis->set(
                    $this->createNamespacedKey($normalizedKey),
                    $this->preSerialize($value)
                ) !== false;
            }
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItems(array $normalizedKeyValuePairs): array
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        $namespacedKeyValuePairs = [];
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            $namespacedKeyValuePairs[$this->createNamespacedKey($normalizedKey)] = $this->preSerialize($value);
        }

        try {
            if ($ttl > 0) {
                if ($this->getCapabilities()->ttlSupported === false) {
                    throw new Exception\UnsupportedMethodCallException(
                        'To use ttl you need redis-server version >= 2.0.0',
                    );
                }

                //mSet does not allow ttl, so use transaction
                $transaction = $redis->multi();
                foreach ($namespacedKeyValuePairs as $key => $value) {
                    $transaction->setex($key, (int) $ttl, $value);
                }
                $success = $transaction->exec() !== false;
            } else {
                $success = $redis->mSet($namespacedKeyValuePairs) !== false;
            }
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }

        if (! $success) {
            throw RedisRuntimeException::fromInternalRedisError($redis);
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function internalAddItem(string $normalizedKey, mixed $value): bool
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        try {
            if ($ttl > 0) {
                if ($this->getCapabilities()->ttlSupported === false) {
                    throw new Exception\UnsupportedMethodCallException(
                        'To use ttl you need redis-server version >= 2.0.0',
                    );
                }

                /**
                 * To ensure expected behaviour, we stick with the "setnx" method.
                 * This means we only set the ttl after the key/value has been successfully set.
                 */
                $success = $redis->setnx(
                    $this->createNamespacedKey($normalizedKey),
                    $this->preSerialize($value)
                ) !== false;
                if ($success) {
                    $redis->expire($this->createNamespacedKey($normalizedKey), (int) $ttl);
                }

                return $success;
            }

            return $redis->setnx($this->createNamespacedKey($normalizedKey), $this->preSerialize($value)) !== false;
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalTouchItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();
        try {
            $ttl = $this->getOptions()->getTtl();
            return (bool) $redis->expire($this->createNamespacedKey($normalizedKey), (int) $ttl);
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalRemoveItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();
        try {
            return (bool) $redis->del($this->createNamespacedKey($normalizedKey));
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        $redis = $this->getRedisResource();
        try {
            return $redis->flushDB();
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clearByNamespace(string $namespace): bool
    {
        /** @psalm-suppress TypeDoesNotContainType Psalm type does not prevent from injecting empty string */
        if ($namespace === '') {
            throw new Exception\InvalidArgumentException('No namespace given');
        }

        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $prefix  = $namespace . $options->getNamespaceSeparator();

        $redis->del($redis->keys($prefix . '*'));

        return true;
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

        $redis     = $this->getRedisResource();
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix    = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator() . $prefix;

        $redis->del($redis->keys($prefix . '*'));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalSpace(): int
    {
        $redis = $this->getRedisResource();
        try {
            $info = $redis->info();
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }

        Assert::isMap($info);
        Assert::keyExists($info, 'used_memory');
        assert(array_key_exists('used_memory', $info), 'Provide info to psalm');
        Assert::natural($info['used_memory']);
        return $info['used_memory'];
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetCapabilities(): Capabilities
    {
        if ($this->capabilities !== null) {
            return $this->capabilities;
        }

        $redisSerializerOptionUsed = $this->getResourceManager()->hasSerializationSupport($this);
        $redisVersion              = $this->getRedisVersion();
        $maxKeyLength              = version_compare($redisVersion, '3', '<') ? 255 : 512_000_000;

        $supportedDataTypes = [
            'NULL'     => 'string',
            'boolean'  => 'string',
            'integer'  => 'string',
            'double'   => 'string',
            'string'   => true,
            'array'    => false,
            'object'   => false,
            'resource' => false,
        ];

        if ($redisSerializerOptionUsed === true) {
            $supportedDataTypes = [
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

        return $this->capabilities = new Capabilities(
            maxKeyLength: $maxKeyLength,
            ttlSupported: version_compare($redisVersion, '2', 'ge'),
            namespaceIsPrefix: true,
            supportedDataTypes: $supportedDataTypes,
            ttlPrecision: 1,
            usesRequestTime: false,
        );
    }

    public function getRedisVersion(): string
    {
        $options            = $this->getOptions();
        $versionFromOptions = $options->getRedisVersion();
        if ($versionFromOptions !== '') {
            return $versionFromOptions;
        }

        $redis = $this->getRedisResource();
        try {
            $info = $redis->info();
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }

        if (! is_array($info)) {
            return '0.0.0-unknown';
        }

        if (! isset($info['redis_version']) || ! is_string($info['redis_version'])) {
            return '0.0.0-unknown';
        }

        $version = $info['redis_version'];
        assert($version !== '');
        $options->setRedisVersion($version);

        return $version;
    }

    public function setResourceManager(RedisResourceManagerInterface $resourceManager): void
    {
        $this->resourceManager = $resourceManager;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetMetadata(string $normalizedKey): Metadata|null
    {
        $redis = $this->getRedisResource();

        try {
            $redisVersion = $this->getRedisVersion();

            if (version_compare($redisVersion, '2.8', '>=')) {
                // redis >= 2.8
                // The command 'pttl' returns -2 if the item does not exist
                // and -1 if the item has no associated expire
                $pttl = $redis->pttl($this->createNamespacedKey($normalizedKey));
                if ($pttl <= -2) {
                    return null;
                }

                if ($pttl === -1) {
                    return new Metadata(remainingTimeToLive: Metadata::TTL_UNLIMITED);
                }

                $ttl = (int) round($pttl / 1000);
                Assert::natural($ttl);
                return new Metadata(remainingTimeToLive: $ttl);
            }

            if (version_compare($redisVersion, '2.6', '>=')) {
                // redis >= 2.6, < 2.8
                // The command 'pttl' returns -1 if the item does not exist or the item has no associated expire
                $pttl = $redis->pttl($this->createNamespacedKey($normalizedKey));
                if ($pttl <= -1) {
                    if (! $this->internalHasItem($normalizedKey)) {
                        return null;
                    }

                    return new Metadata(remainingTimeToLive: Metadata::TTL_UNLIMITED);
                }

                $ttl = (int) round($pttl / 1000);
                Assert::natural($ttl);
                return new Metadata(remainingTimeToLive: $ttl);
            }

            if (version_compare($redisVersion, '2', '>=')) {
                // redis >= 2, < 2.6
                // The command 'pttl' is not supported but 'ttl'
                // The command 'ttl' returns 0 if the item does not exist same as if the item is going to be expired
                // NOTE: In case of ttl=0 we return false because the item is going to be expired in a very near future
                //       and then doesn't exist anymore
                $ttl = $redis->ttl($this->createNamespacedKey($normalizedKey));
                if ($ttl <= -1) {
                    if (! $this->internalHasItem($normalizedKey)) {
                        return null;
                    }

                    return new Metadata(remainingTimeToLive: Metadata::TTL_UNLIMITED);
                }

                Assert::natural($ttl);
                return new Metadata(remainingTimeToLive: $ttl);
            } elseif (! $this->internalHasItem($normalizedKey)) {
                return null;
            }
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }

        return new Metadata(remainingTimeToLive: null);
    }

    /**
     * Pre-Serialize value before putting it to the redis extension
     * The reason for this is the buggy extension version < 2.5.7
     * which is producing a segfault on storing NULL as long as no serializer was configured.
     *
     * @link https://github.com/zendframework/zend-cache/issues/88
     */
    private function preSerialize(mixed $value): mixed
    {
        $resourceManager = $this->getResourceManager();
        if (! $resourceManager->hasSerializationSupport($this)) {
            return (string) $value;
        }

        return $value;
    }

    private function getResourceManager(): RedisResourceManagerInterface
    {
        if ($this->resourceManager !== null) {
            return $this->resourceManager;
        }

        return $this->resourceManager = new RedisResourceManager($this->getOptions());
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
     * This method verifies that the return value from {@see RedisClusterFromExtension::get} or
     * {@see RedisClusterFromExtension::mget} is `false` because the key does not exist or because the keys value
     * is `false` at type-level.
     */
    private function isFalseReturnValuePersisted(RedisFromExtension $redis, string $key): bool
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
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $redis);
        }
    }
}
