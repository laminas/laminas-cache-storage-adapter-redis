<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\CacheItemPool;

use Cache\IntegrationTests\CachePoolTest;
use Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use Psr\Cache\CacheItemPoolInterface;
use RedisCluster;

use function get_class;
use function sprintf;

final class RedisClusterWithPhpIgbinaryTest extends CachePoolTest
{
    use RedisClusterStorageCreationTrait;

    public function createCachePool(): CacheItemPoolInterface
    {
        $storage = $this->createRedisClusterStorage(RedisCluster::SERIALIZER_IGBINARY, false);
        /** @psalm-suppress MixedArrayAssignment */
        $this->skippedTests['testHasItemReturnsFalseWhenDeferredItemIsExpired'] = sprintf(
            '%s storage doesn\'t support driver deferred',
            get_class($storage)
        );

        return new CacheItemPoolDecorator($storage);
    }
}
