<?php

namespace LaminasTest\Cache\Psr\SimpleCache;

use Cache\IntegrationTests\SimpleCacheTest;
use Composer\InstalledVersions;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use Psr\SimpleCache\CacheInterface;
use Redis;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function is_string;
use function version_compare;

final class RedisIntegrationTest extends SimpleCacheTest
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
        // set non-UTC timezone
        $this->tz = date_default_timezone_get();
        date_default_timezone_set('America/Vancouver');
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

        parent::setUp();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->tz);

        parent::tearDown();
    }

    public function createSimpleCache(): CacheInterface
    {
        return new SimpleCacheDecorator($this->createRedisStorage(
            Redis::SERIALIZER_NONE,
            true
        ));
    }
}
