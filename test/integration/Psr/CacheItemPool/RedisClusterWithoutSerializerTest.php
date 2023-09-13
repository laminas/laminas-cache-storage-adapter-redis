<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\CacheItemPool;

use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCacheItemPoolIntegrationTest;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use Redis;
use RedisCluster;

use function sprintf;

final class RedisClusterWithoutSerializerTest extends AbstractCacheItemPoolIntegrationTest
{
    use RedisClusterStorageCreationTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skippedTests['testHasItemReturnsFalseWhenDeferredItemIsExpired'] = sprintf(
            '%s storage doesn\'t support driver deferred',
            RedisCluster::class
        );
    }

    protected function createStorage(): StorageInterface
    {
        return $this->createRedisClusterStorage(Redis::SERIALIZER_NONE, true);
    }
}
