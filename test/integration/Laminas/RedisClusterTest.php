<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\Redis\Metadata;
use Laminas\Cache\Storage\Adapter\RedisCluster;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Adapter\RedisClusterOptionsFromIni;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCommonAdapterTest;
use Redis as RedisFromExtension;

/**
 * @template-extends AbstractCommonAdapterTest<RedisClusterOptions,RedisCluster>
 */
final class RedisClusterTest extends AbstractCommonAdapterTest
{
    use RedisClusterStorageCreationTrait;

    public function testWillProperlyFlush(): void
    {
        $storage = $this->storage;
        self::assertInstanceOf(StorageInterface::class, $storage);
        $storage->setItem('foo', 'bar');
        $flushed = $storage->flush();
        self::assertTrue($flushed);
        self::assertFalse($storage->hasItem('foo'));
    }

    public function testCanCreateResourceFromSeeds(): void
    {
        $name = $this->getClusterNameFromEnvironment();
        self::assertNotEmpty($name, 'Missing cluster name environment configuration.');
        $optionsFromIni = new RedisClusterOptionsFromIni();
        $options        = new RedisClusterOptions([
            'seeds' => $optionsFromIni->getSeeds($name),
        ]);

        $storage = new RedisCluster($options);
        self::assertTrue($storage->flush());
    }

    public function testWillHandleIntegratedSerializerInformation(): void
    {
        $storage = $this->storage;
        self::assertInstanceOf(StorageInterface::class, $storage);
        $this->removeSerializer($storage);

        $options = $storage->getOptions();
        $options->setLibOptions([
            RedisFromExtension::OPT_SERIALIZER => RedisFromExtension::SERIALIZER_PHP,
        ]);

        $capabilities = $storage->getCapabilities();
        $dataTypes    = $capabilities->supportedDataTypes;
        self::assertEquals([
            'NULL'     => true,
            'boolean'  => true,
            'integer'  => true,
            'double'   => true,
            'string'   => true,
            'array'    => 'array',
            'object'   => 'object',
            'resource' => false,
        ], $dataTypes);
    }

    private function removeSerializer(RedisCluster $storage): void
    {
        foreach ($storage->getPluginRegistry() as $plugin) {
            if (! $plugin instanceof Serializer) {
                continue;
            }

            $storage->removePlugin($plugin);
        }
    }

    public function testWillHandleNonSupportedSerializerInformation(): void
    {
        $storage = $this->storage;
        self::assertInstanceOf(StorageInterface::class, $storage);
        $this->removeSerializer($storage);
        $options = $storage->getOptions();
        $options->setLibOptions([
            RedisFromExtension::OPT_SERIALIZER => RedisFromExtension::SERIALIZER_NONE,
        ]);

        $capabilities = $storage->getCapabilities();
        $dataTypes    = $capabilities->supportedDataTypes;
        self::assertEquals([
            'NULL'     => 'string',
            'boolean'  => 'string',
            'integer'  => 'string',
            'double'   => 'string',
            'string'   => true,
            'array'    => false,
            'object'   => false,
            'resource' => false,
        ], $dataTypes);
    }

    public function testClearsByNamespace(): void
    {
        $namespace        = 'foo';
        $anotherNamespace = 'bar';
        $storage          = $this->storage;
        self::assertInstanceOf(StorageInterface::class, $storage);
        $options = $storage->getOptions();
        $options->setNamespace($namespace);

        $storage->setItem('bar', 'baz');
        $storage->setItem('qoo', 'ooq');

        $options->setNamespace($anotherNamespace);

        $storage->setItem('bar', 'baz');
        $storage->setItem('qoo', 'ooq');

        $storage->clearByNamespace($namespace);

        $options->setNamespace($namespace);

        $result = $storage->getItems(['bar', 'qoo']);
        self::assertEmpty($result);

        $options->setNamespace($anotherNamespace);
        $result = $storage->getItems(['bar', 'qoo']);
        self::assertEquals($result['bar'], 'baz');
        self::assertEquals($result['qoo'], 'ooq');
    }

    protected function setUp(): void
    {
        $this->storage = $this->createRedisClusterStorage(
            RedisFromExtension::SERIALIZER_PHP,
            false
        );
        $this->options = $this->storage->getOptions();

        // Clear storage before executing tests.
        $this->storage->flush();

        parent::setUp();
    }

    public function testTouchItem(): void
    {
        $key = 'key';

        // no TTL
        $this->storage->getOptions()->setTtl(0);
        $this->storage->setItem($key, 'val');
        $metadata = $this->storage->getMetadata($key);
        self::assertNotNull($metadata);
        self::assertEquals(Metadata::TTL_UNLIMITED, $metadata->remainingTimeToLive);

        // touch with a specific TTL will add this TTL
        $ttl = 1000;
        $this->storage->getOptions()->setTtl($ttl);
        self::assertTrue($this->storage->touchItem($key));
        $metadata = $this->storage->getMetadata($key);
        self::assertNotNull($metadata);
        self::assertEquals($ttl, $metadata->remainingTimeToLive);
    }
}
