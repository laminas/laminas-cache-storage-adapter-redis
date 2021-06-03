<?php

namespace LaminasTest\Cache\Psr\CacheItemPool;

use Cache\IntegrationTests\CachePoolTest;
use Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use Psr\Cache\CacheItemPoolInterface;
use Redis;

use function date_default_timezone_get;
use function date_default_timezone_set;

class RedisIntegrationTest extends CachePoolTest
{
    use RedisStorageCreationTrait;

    /**
     * Backup default timezone
     *
     * @var string
     */
    private $tz;

    protected function setUp(): void
    {
        /** @psalm-suppress MixedArrayAssignment */
        $this->skippedTests['testHasItemReturnsFalseWhenDeferredItemIsExpired']
            = 'Cache decorator does not support deferred deletion';

        // set non-UTC timezone
        $this->tz = date_default_timezone_get();
        date_default_timezone_set('America/Vancouver');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->tz);

        parent::tearDown();
    }

    public function createCachePool(): CacheItemPoolInterface
    {
        return new CacheItemPoolDecorator($this->createRedisStorage(
            Redis::SERIALIZER_NONE,
            true
        ));
    }
}
