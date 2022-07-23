<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\CacheItemPool;

use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCacheItemPoolIntegrationTest;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use Redis;

class RedisIntegrationTest extends AbstractCacheItemPoolIntegrationTest
{
    use RedisStorageCreationTrait;

    protected function setUp(): void
    {
        /** @psalm-suppress MixedArrayAssignment */
        $this->skippedTests['testHasItemReturnsFalseWhenDeferredItemIsExpired']
            = 'Cache decorator does not support deferred deletion';

        parent::setUp();
    }

    protected function createStorage(): StorageInterface
    {
        return $this->createRedisStorage(
            Redis::SERIALIZER_NONE,
            true
        );
    }
}
