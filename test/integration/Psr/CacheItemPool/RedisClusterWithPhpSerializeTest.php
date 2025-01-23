<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\CacheItemPool;

use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCacheItemPoolIntegrationTest;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use Redis;

/**
 * @uses FlushableInterface
 *
 * @template-extends AbstractCacheItemPoolIntegrationTest<RedisClusterOptions>
 */
final class RedisClusterWithPhpSerializeTest extends AbstractCacheItemPoolIntegrationTest
{
    use RedisClusterStorageCreationTrait;

    protected function createStorage(): StorageInterface&FlushableInterface
    {
        return $this->createRedisClusterStorage(Redis::SERIALIZER_PHP, false);
    }
}
