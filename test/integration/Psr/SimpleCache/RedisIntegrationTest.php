<?php

namespace LaminasTest\Cache\Psr\SimpleCache;

use Cache\IntegrationTests\SimpleCacheTest;
use Composer\InstalledVersions;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\StorageFactory;
use Psr\SimpleCache\CacheInterface;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function getenv;
use function is_string;
use function version_compare;

class RedisIntegrationTest extends SimpleCacheTest
{
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
                = 'Long keys will be supported for the redis adapter with 2.12+ of `laminas-cache`';
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
        $options = ['resource_id' => self::class];

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') && getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST'), getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')];
        } elseif (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')];
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE')) {
            $options['database'] = getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE');
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD')) {
            $options['password'] = getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD');
        }

        $storage = StorageFactory::adapterFactory('redis', $options);
        $storage->addPlugin(new Serializer());
        return new SimpleCacheDecorator($storage);
    }
}
