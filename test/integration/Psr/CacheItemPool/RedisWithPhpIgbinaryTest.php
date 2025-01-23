<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\CacheItemPool;

use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCacheItemPoolIntegrationTest;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use Redis;

/**
 * @uses FlushableInterface
 *
 * @template-extends AbstractCacheItemPoolIntegrationTest<RedisOptions>
 */
final class RedisWithPhpIgbinaryTest extends AbstractCacheItemPoolIntegrationTest
{
    use RedisStorageCreationTrait;

    protected function createStorage(): StorageInterface&FlushableInterface
    {
        return $this->createRedisStorage(Redis::SERIALIZER_IGBINARY, false);
    }
}
