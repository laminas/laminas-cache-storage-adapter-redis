<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Adapter\RedisResourceManager;
use Laminas\Cache\Storage\Plugin\Serializer;
use LaminasTest\Cache\Storage\Adapter\AbstractCommonAdapterTest;
use PHPUnit\Framework\MockObject\MockObject;
use Redis as RedisResource;
use Throwable;

use function ceil;
use function count;
use function getenv;

/**
 * @covers Redis<extended>
 * @template-extends AbstractCommonAdapterTest<Redis, RedisOptions>
 */
final class RedisTest extends AbstractCommonAdapterTest
{
    /** @var RedisOptions */
    protected $options;

    /** @var Redis */
    protected $storage;

    public function setUp(): void
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

        $this->options = new RedisOptions($options);
        $this->storage = new Redis($this->options);

        parent::setUp();
    }

    public function tearDown(): void
    {
        if ($this->storage) {
            try {
                $this->storage->flush();
            } catch (Throwable $exception) {
            }
        }

        parent::tearDown();
    }

    public function testLibOptionsFirst(): void
    {
        $options = [
            'resource_id' => self::class . '2',
            'liboptions'  => [
                RedisResource::OPT_SERIALIZER => RedisResource::SERIALIZER_PHP,
            ],
        ];

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

        $redisOptions = new RedisOptions($options);
        $storage      = new Redis($redisOptions);

        $this->assertInstanceOf(Redis::class, $storage);
    }

    public function testRedisSerializer(): void
    {
        $this->storage->addPlugin(new Serializer());
        $value = ['test', 'of', 'array'];
        $this->storage->setItem('key', $value);

        $this->assertCount(count($value), $this->storage->getItem('key'), 'Problem with Redis serialization');
    }

    public function testRedisSetInt(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 123));
        $this->assertEquals('123', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testRedisSetDouble(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 123.12));
        $this->assertEquals('123.12', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testRedisSetNull(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, null));
        $this->assertEquals('', $this->storage->getItem($key), 'Null should be cast to string');
    }

    public function testRedisSetBoolean(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, true));
        $this->assertEquals('1', $this->storage->getItem($key), 'Boolean should be cast to string');
        $this->assertTrue($this->storage->setItem($key, false));
        $this->assertEquals('', $this->storage->getItem($key), 'Boolean should be cast to string');
    }

    public function testGetCapabilitiesTtl(): void
    {
        $resourceManager = $this->options->getResourceManager();
        $resourceId      = $this->options->getResourceId();
        $redis           = $resourceManager->getResource($resourceId);
        $majorVersion    = (int) $redis->info()['redis_version'];

        $this->assertEquals($majorVersion, $resourceManager->getMajorVersion($resourceId));

        $capabilities = $this->storage->getCapabilities();
        if ($majorVersion < 2) {
            $this->assertEquals(0, $capabilities->getMinTtl(), 'Redis version < 2.0.0 does not support key expiration');
        } else {
            $this->assertEquals(1, $capabilities->getMinTtl(), 'Redis version > 2.0.0 supports key expiration');
        }
    }

    public function testSocketConnection(): void
    {
        $socket = '/tmp/redis.sock';
        $this->options->getResourceManager()->setServer($this->options->getResourceId(), $socket);
        $normalized = $this->options->getResourceManager()->getServer($this->options->getResourceId());
        $this->assertEquals($socket, $normalized['host'], 'Host should equal to socket {$socket}');

        // Don't try to flush on shutdown
        $this->storage = null;
    }

    public function testGetSetDatabase(): void
    {
        $this->assertTrue($this->storage->setItem('key', 'val'));
        $this->assertEquals('val', $this->storage->getItem('key'));

        $databaseNumber  = 1;
        $resourceManager = $this->options->getResourceManager();
        $resourceManager->setDatabase($this->options->getResourceId(), $databaseNumber);
        $this->assertNull(
            $this->storage->getItem('key'),
            'No value should be found because set was done on different database than get'
        );
        $this->assertEquals(
            $databaseNumber,
            $resourceManager->getDatabase($this->options->getResourceId()),
            'Incorrect database was returned'
        );
    }

    public function testGetSetPassword(): void
    {
        $pass = 'super secret';
        $this->options->getResourceManager()->setPassword($this->options->getResourceId(), $pass);
        $this->assertEquals(
            $pass,
            $this->options->getResourceManager()->getPassword($this->options->getResourceId()),
            'Password was not correctly set'
        );
    }

    public function testGetSetLibOptionsOnExistingRedisResourceInstance(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);

        $value = ['value'];
        $key   = 'key';
        //test if it's still possible to set/get item and if lib serializer works
        $this->storage->setItem($key, $value);

        $this->assertEquals(
            $value,
            $this->storage->getItem($key),
            'Redis should return an array, lib options were not set correctly'
        );

        $options = ['serializer' => RedisResource::SERIALIZER_NONE];
        $this->options->setLibOptions($options);
        $this->storage->setItem($key, $value);
        //should not serialize array correctly
        $this->assertIsNotArray(
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
        $this->assertEquals(
            $value,
            $redis->getItem($key),
            'Redis should return an array, lib options were not set correctly'
        );

        $options = ['serializer' => RedisResource::SERIALIZER_NONE];
        $this->options->setLibOptions($options);
        $redis->setItem($key, $value);
        //should not serialize array correctly
        $this->assertIsNotArray(
            $redis->getItem($key),
            'Redis should not serialize automatically anymore, lib options were not set correctly'
        );
    }

    public function testGetSetNamespace(): void
    {
        $namespace = 'testNamespace';
        $this->options->setNamespace($namespace);
        $this->assertEquals($namespace, $this->options->getNamespace(), 'Namespace was not set correctly');
    }

    public function testGetSetNamespaceSeparator(): void
    {
        $separator = '/';
        $this->options->setNamespaceSeparator($separator);
        $this->assertEquals($separator, $this->options->getNamespaceSeparator(), 'Separator was not set correctly');
    }

    public function testGetSetResourceManager(): void
    {
        $resourceManager = new RedisResourceManager();
        $options         = new RedisOptions();
        $options->setResourceManager($resourceManager);
        $this->assertInstanceOf(
            RedisResourceManager::class,
            $options->getResourceManager(),
            'Wrong resource manager retuned, it should of type RedisResourceManager'
        );

        $this->assertEquals($resourceManager, $options->getResourceManager());
    }

    public function testGetSetResourceId(): void
    {
        $resourceId = '1';
        $options    = new RedisOptions();
        $options->setResourceId($resourceId);
        $this->assertEquals($resourceId, $options->getResourceId(), 'Resource id was not set correctly');
    }

    public function testGetSetPersistentId(): void
    {
        $persistentId = '1';
        $this->options->setPersistentId($persistentId);
        $this->assertEquals($persistentId, $this->options->getPersistentId(), 'Persistent id was not set correctly');
    }

    public function testOptionsGetSetLibOptions(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);
        $this->assertEquals(
            $options,
            $this->options->getLibOptions(),
            'Lib Options were not set correctly through RedisOptions'
        );
    }

    public function testGetSetServer(): void
    {
        $server = [
            'host'    => '127.0.0.1',
            'port'    => 6379,
            'timeout' => 0,
        ];
        $this->options->setServer($server);
        $this->assertEquals($server, $this->options->getServer(), 'Server was not set correctly through RedisOptions');

        // Don't try to flush on shutdown
        $this->storage = null;
    }

    public function testOptionsGetSetDatabase(): void
    {
        $database = 1;
        $this->options->setDatabase($database);
        $this->assertEquals($database, $this->options->getDatabase(), 'Database not set correctly using RedisOptions');
    }

    public function testOptionsGetSetPassword(): void
    {
        $password = 'my-secret';
        $this->options->setPassword($password);
        $this->assertEquals(
            $password,
            $this->options->getPassword(),
            'Password was set incorrectly using RedisOptions'
        );
    }

    public function testTouchItem(): void
    {
        $key = 'key';

        // no TTL
        $this->storage->getOptions()->setTtl(0);
        $this->storage->setItem($key, 'val');
        $this->assertEquals(0, $this->storage->getMetadata($key)['ttl']);

        // touch with a specific TTL will add this TTL
        $ttl = 1000;
        $this->storage->getOptions()->setTtl($ttl);
        $this->assertTrue($this->storage->touchItem($key));
        $this->assertEquals($ttl, ceil($this->storage->getMetadata($key)['ttl']));
    }

    public function testHasItemReturnsFalseIfRedisExistsReturnsZero(): void
    {
        $redis = $this->mockInitializedRedisResource();
        $redis->method('exists')->willReturn(0);
        $adapter = $this->createAdapterFromResource($redis);

        $hasItem = $adapter->hasItem('does-not-exist');

        $this->assertFalse($hasItem);
    }

    public function testHasItemReturnsTrueIfRedisExistsReturnsNonZeroInt(): void
    {
        $redis = $this->mockInitializedRedisResource();
        $redis->method('exists')->willReturn(23);
        $adapter = $this->createAdapterFromResource($redis);

        $hasItem = $adapter->hasItem('does-not-exist');

        $this->assertTrue($hasItem);
    }

    /**
     * @return Redis
     */
    private function createAdapterFromResource(RedisResource $redis)
    {
        $resourceManager = new RedisResourceManager();
        $resourceId      = 'my-resource';
        $resourceManager->setResource($resourceId, $redis);
        $options = new RedisOptions(['resource_manager' => $resourceManager, 'resource_id' => $resourceId]);
        return new Redis($options);
    }

    /**
     * @return MockObject&RedisResource
     */
    private function mockInitializedRedisResource()
    {
        $redis         = $this->createMock(RedisFromExtensionAsset::class);
        $redis->socket = true;
        $redis->method('info')->willReturn(['redis_version' => '0.0.0-unknown']);
        return $redis;
    }
}
