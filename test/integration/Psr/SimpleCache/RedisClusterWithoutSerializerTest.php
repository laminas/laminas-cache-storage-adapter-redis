<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\SimpleCache;

use Cache\IntegrationTests\SimpleCacheTest;
use Composer\InstalledVersions;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use Psr\SimpleCache\CacheInterface;
use RedisCluster;

use function is_string;
use function version_compare;

final class RedisClusterWithoutSerializerTest extends SimpleCacheTest
{
    use RedisClusterStorageCreationTrait;

    public function createSimpleCache(): CacheInterface
    {
        $storage = $this->createRedisClusterStorage(RedisCluster::SERIALIZER_NONE, true);

        return new SimpleCacheDecorator($storage);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $laminasCacheVersion = InstalledVersions::getVersion('laminas/laminas-cache');
        if (! is_string($laminasCacheVersion)) {
            self::fail('Could not determine `laminas-cache` version!');
        }

        if (
            version_compare(
                $laminasCacheVersion,
                '2.12',
                'lt'
            )
        ) {
            /** @psalm-suppress MixedArrayAssignment */
            $this->skippedTests['testBasicUsageWithLongKey']
                = 'Long keys will be supported for the redis adapter with `laminas-cache` v2.12+';
        }
    }
}
