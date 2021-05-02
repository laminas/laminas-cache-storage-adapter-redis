<?php

/**
 * @see       https://github.com/laminas/laminas-cache for the canonical source repository
 * @copyright https://github.com/laminas/laminas-cache/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-cache/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Cache\Psr\SimpleCache;

use Cache\IntegrationTests\SimpleCacheTest;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\StorageFactory;
use Psr\SimpleCache\CacheInterface;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function getenv;

class RedisIntegrationTest extends SimpleCacheTest
{
    /**
     * Backup default timezone
     *
     * @var string
     */
    private $tz;

    /** @var Redis */
    private $storage;

    protected function setUp(): void
    {
        // set non-UTC timezone
        $this->tz = date_default_timezone_get();
        date_default_timezone_set('America/Vancouver');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->tz);

        if ($this->storage) {
            $this->storage->flush();
        }

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
