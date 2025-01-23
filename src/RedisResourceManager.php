<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\RedisRuntimeException;
use Laminas\Cache\Storage\Plugin\PluginInterface;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\Storage\PluginCapableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Redis as RedisFromExtension;
use RedisException as RedisFromExtensionException;

use function array_filter;
use function is_array;

/**
 * @uses PluginCapableInterface
 * @uses StorageInterface
 */
final class RedisResourceManager implements RedisResourceManagerInterface
{
    private const DEFAULT_REDIS_PORT = 6379;

    public function __construct(
        private readonly RedisOptions $options,
    ) {
    }

    public function getResource(): RedisFromExtension
    {
        try {
            $resource = $this->createRedisFromExtension($this->options);
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromFailedConnection($exception);
        }

        $libraryOptions = $this->options->getLibOptions();

        try {
            $resource = $this->applyLibraryOptions($resource, $libraryOptions);
        } catch (RedisFromExtensionException $exception) {
            throw RedisRuntimeException::fromRedisException($exception, $resource);
        }

        return $resource;
    }

    private function createRedisFromExtension(RedisOptions $options): RedisFromExtension
    {
        $server = $options->getServer();
        $host   = $server;
        $port   = null;
        if (is_array($server)) {
            $host = $server['host'];
            $port = $server['port'] ?? self::DEFAULT_REDIS_PORT;
        }

        $authentication = [];
        $user           = $options->getUser();
        $password       = $options->getPassword();

        if ($user !== null) {
            $authentication[] = $user;
        }

        if ($password !== null) {
            $authentication[] = $password;
        }

        if ($authentication === []) {
            $authentication = null;
        }

        $resourceOptions = [
            'host'           => $host,
            'port'           => $port,
            'connectTimeout' => $server['timeout'] ?? null,
            'persistent'     => $options->getPersistentId() ?? $options->isPersistent(),
            'auth'           => $authentication,
        ];

        $resource = new RedisFromExtension(array_filter($resourceOptions, fn (mixed $value) => $value !== null));
        $resource->select($options->getDatabase());

        return $resource;
    }

    /**
     * @param array<positive-int,mixed> $options
     */
    private function applyLibraryOptions(RedisFromExtension $resource, array $options): RedisFromExtension
    {
        foreach ($options as $option => $value) {
            $resource->setOption($option, $value);
        }

        return $resource;
    }

    public function hasSerializationSupport(StorageInterface&PluginCapableInterface $adapter): bool
    {
        /**
         * NOTE: we are not using {@see RedisFromExtensionManager::getLibOption} here
         *       as this would create a connection to redis even tho it won't be needed.
         *       Theoretically, it would be possible for upstream projects to receive the resource directly from the
         *       resource manager and then apply changes to it. As this is not the common use-case, this is not
         *       considered in this check.
         */
        $options    = $adapter->getOptions();
        $serializer = $options->getLibOption(
            RedisFromExtension::OPT_SERIALIZER,
            RedisFromExtension::SERIALIZER_NONE
        );

        if ($serializer !== RedisFromExtension::SERIALIZER_NONE) {
            return true;
        }

        /** @var iterable<PluginInterface> $plugins */
        $plugins = $adapter->getPluginRegistry();
        foreach ($plugins as $plugin) {
            if (! $plugin instanceof Serializer) {
                continue;
            }

            return true;
        }

        return false;
    }
}
