<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Laminas\Cache\Storage\Adapter\Exception\RedisRuntimeException;
use Laminas\Cache\Storage\Plugin\PluginInterface;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\Storage\PluginCapableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Redis as RedisFromExtension;
use RedisCluster as RedisClusterFromExtension;
use RedisClusterException;
use Throwable;

/**
 * @uses PluginCapableInterface
 */
final class RedisClusterResourceManager implements RedisClusterResourceManagerInterface
{
    public function __construct(private readonly RedisClusterOptions $options)
    {
    }

    public function getResource(): RedisClusterFromExtension
    {
        try {
            $resource = $this->createRedisResource($this->options);
        } catch (RedisClusterException $exception) {
            throw RedisRuntimeException::fromFailedConnection($exception);
        }

        $libraryOptions = $this->options->getLibOptions();

        try {
            $resource = $this->applyLibraryOptions($resource, $libraryOptions);
        } catch (RedisClusterException $exception) {
            throw RedisRuntimeException::fromClusterException($exception, $resource);
        }

        return $resource;
    }

    private function createRedisResource(RedisClusterOptions $options): RedisClusterFromExtension
    {
        $authenticationInfo = RedisAuthenticationInfo::fromOptions($options);

        if ($options->hasName()) {
            return $this->createRedisResourceFromName(
                $options->getName(),
                $options->getTimeout(),
                $options->getReadTimeout(),
                $options->isPersistent(),
                $authenticationInfo,
                $options->getSslContext()
            );
        }

        /**
         * Psalm currently (<= 5.26.1) uses an outdated (phpredis < 5.3.2) constructor signature for the RedisCluster
         * class in the phpredis extension.
         *
         * @psalm-suppress InvalidArgument
         */
        return new RedisClusterFromExtension(
            null,
            $options->getSeeds(),
            $options->getTimeout(),
            $options->getReadTimeout(),
            $options->isPersistent(),
            $authenticationInfo?->toRedisAuthInfo(),
            $options->getSslContext()?->toSslContextArray()
        );
    }

    /**
     * @psalm-param non-empty-string $name
     */
    private function createRedisResourceFromName(
        string $name,
        float $fallbackTimeout,
        float $fallbackReadTimeout,
        bool $persistent,
        RedisAuthenticationInfo|null $fallbackAuthentication,
        ?SslContext $sslContext
    ): RedisClusterFromExtension {
        try {
            $options = new RedisClusterOptionsFromIni();
        } catch (InvalidRedisClusterConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new InvalidRedisClusterConfigurationException($throwable->getMessage(), previous: $throwable);
        }

        $seeds          = $options->getSeeds($name);
        $timeout        = $options->getTimeout($name, $fallbackTimeout);
        $readTimeout    = $options->getReadTimeout($name, $fallbackReadTimeout);
        $password       = $options->getPasswordByName($name, '');
        $authentication = $password === '' ? $fallbackAuthentication?->toRedisAuthInfo() : $password;

        /**
         * Psalm currently (<= 5.26.1) uses an outdated (phpredis < 5.3.2) constructor signature for the RedisCluster
         * class in the phpredis extension.
         *
         * @psalm-suppress PossiblyInvalidArgument
         */
        return new RedisClusterFromExtension(
            null,
            $seeds,
            $timeout,
            $readTimeout,
            $persistent,
            $authentication,
            $sslContext?->toSslContextArray()
        );
    }

    /**
     * @psalm-param array<positive-int,mixed> $options
     */
    private function applyLibraryOptions(
        RedisClusterFromExtension $resource,
        array $options
    ): RedisClusterFromExtension {
        foreach ($options as $option => $value) {
            /**
             * @see https://github.com/phpredis/phpredis#setoption
             *
             * @psalm-suppress MixedArgument
             */
            $resource->setOption($option, $value);
        }

        return $resource;
    }

    public function hasSerializationSupport(PluginCapableInterface&StorageInterface $adapter): bool
    {
        /**
         * NOTE: we are not using {@see RedisClusterResourceManager::getLibOption} here
         *       as this would create a connection to redis even tho it wont be needed.
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
