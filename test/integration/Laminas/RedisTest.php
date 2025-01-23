<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Serializer\AdapterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use LaminasTest\Cache\Storage\Adapter\AbstractCommonAdapterTest;
use Redis as RedisResource;

use function count;
use function getenv;

/**
 * @covers Redis<extended>
 * @template-extends AbstractCommonAdapterTest<RedisOptions, Redis>
 */
final class RedisTest extends AbstractCommonAdapterTest
{
    public function setUp(): void
    {
        $options = [];

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') && getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')) {
            $options['server'] = [
                (string) getenv('TESTS_LAMINAS_CACHE_REDIS_HOST'),
                (int) getenv('TESTS_LAMINAS_CACHE_REDIS_PORT'),
            ];
        } elseif (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')) {
            $options['server'] = [(string) getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')];
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE')) {
            $options['database'] = (int) getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE');
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD')) {
            $options['password'] = (string) getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD');
        }

        $this->options = new RedisOptions($options);
        $this->storage = new Redis($this->options);

        parent::setUp();
    }

    public function testLibOptionsFirst(): void
    {
        $options = [
            'liboptions' => [
                RedisResource::OPT_SERIALIZER => RedisResource::SERIALIZER_PHP,
            ],
        ];

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') && getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')) {
            $options['server'] = [
                getenv('TESTS_LAMINAS_CACHE_REDIS_HOST'),
                (int) getenv('TESTS_LAMINAS_CACHE_REDIS_PORT'),
            ];
        } elseif (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')];
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE')) {
            $options['database'] = (int) getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE');
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD')) {
            $options['password'] = getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD');
        }

        $redisOptions = new RedisOptions($options);
        $storage      = new Redis($redisOptions);

        self::assertInstanceOf(Redis::class, $storage);
    }

    public function testRedisSerializer(): void
    {
        $this->storage->addPlugin(new Serializer(new AdapterPluginManager(new ServiceManager())));
        $value = ['test', 'of', 'array'];
        $this->storage->setItem('key', $value);

        self::assertCount(count($value), $this->storage->getItem('key'), 'Problem with Redis serialization');
    }

    public function testRedisSetInt(): void
    {
        $key = 'key';
        self::assertTrue($this->storage->setItem($key, 123));
        self::assertEquals('123', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testRedisSetDouble(): void
    {
        $key = 'key';
        self::assertTrue($this->storage->setItem($key, 123.12));
        self::assertEquals('123.12', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testRedisSetNull(): void
    {
        $key = 'key';
        self::assertTrue($this->storage->setItem($key, null));
        self::assertEquals('', $this->storage->getItem($key), 'Null should be cast to string');
    }

    public function testRedisSetBoolean(): void
    {
        $key = 'key';
        self::assertTrue($this->storage->setItem($key, true));
        self::assertEquals('1', $this->storage->getItem($key), 'Boolean should be cast to string');
        self::assertTrue($this->storage->setItem($key, false));
        self::assertEquals('', $this->storage->getItem($key), 'Boolean should be cast to string');
    }

    public function testGetSetLibOptionsOnExistingRedisResourceInstance(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);

        $value = ['value'];
        $key   = 'key';
        //test if it's still possible to set/get item and if lib serializer works
        $this->storage->setItem($key, $value);

        self::assertEquals(
            $value,
            $this->storage->getItem($key),
            'Redis should return an array, lib options were not set correctly'
        );

        $options = ['serializer' => RedisResource::SERIALIZER_NONE];
        $this->options->setLibOptions($options);
        $this->storage->setItem($key, $value);
        //should not serialize array correctly
        self::assertIsNotArray(
            $this->storage->getItem($key),
            'Redis should not serialize automatically anymore, lib options were not set correctly'
        );
    }

    public function testGetSetLibOptionsWithCleanRedisResourceInstance(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);

        $redis = new Redis($this->options);
        $value = ['value'];
        $key   = 'key';
        //test if it's still possible to set/get item and if lib serializer works
        $redis->setItem($key, $value);
        self::assertEquals(
            $value,
            $redis->getItem($key),
            'Redis should return an array, lib options were not set correctly'
        );

        $options = ['serializer' => RedisResource::SERIALIZER_NONE];
        $this->options->setLibOptions($options);
        $redis->setItem($key, $value);
        //should not serialize array correctly
        self::assertIsNotArray(
            $redis->getItem($key),
            'Redis should not serialize automatically anymore, lib options were not set correctly'
        );
    }

    public function testTouchItem(): void
    {
        $key = 'key';

        // no TTL
        $this->storage->getOptions()->setTtl(0);
        $this->storage->setItem($key, 'val');
        $metadata = $this->storage->getMetadata($key);
        self::assertNotNull($metadata);
        self::assertEquals(Redis\Metadata::TTL_UNLIMITED, $metadata->remainingTimeToLive);

        // touch with a specific TTL will add this TTL
        $ttl = 1000;
        $this->storage->getOptions()->setTtl($ttl);
        self::assertTrue($this->storage->touchItem($key));
        $metadata = $this->storage->getMetadata($key);
        self::assertNotNull($metadata);
        self::assertEquals($ttl, $metadata->remainingTimeToLive);
    }

    public function testGetVersionFromRedisServer(): void
    {
        $host            = getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') ?: 'localhost';
        $port            = (int) (getenv('TESTS_LAMINAS_CACHE_REDIS_PORT') ?: 6379);
        $options         = new RedisOptions(['server' => ['host' => $host, 'port' => $port]]);
        $resourceManager = new Redis($options);

        self::assertMatchesRegularExpression(
            '#^\d+\.\d+\.\d+#',
            $resourceManager->getRedisVersion(),
            'Version from redis is expected to match semver.',
        );
    }

    public function testOptionsFluentInterface(): void
    {
        self::markTestSkipped('This test does actually use ');
    }
}
