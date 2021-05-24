<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\Adapter\RedisCluster;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Adapter\RedisClusterOptionsFromIni;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCommonAdapterTest;
use RedisCluster as RedisClusterFromExtension;

final class RedisClusterTest extends AbstractCommonAdapterTest
{
    use RedisClusterStorageCreationTrait;

    public function testWillProperlyFlush(): void
    {
        $storage = $this->storage;
        self::assertInstanceOf(StorageInterface::class, $storage);
        $storage->setItem('foo', 'bar');
        $flushed = $storage->flush();
        $this->assertTrue($flushed);
        $this->assertFalse($storage->hasItem('foo'));
    }

    public function testCanCreateResourceFromSeeds(): void
    {
        $name = $this->getClusterNameFromEnvironment();
        self::assertNotEmpty($name, 'Missing cluster name environment configuration.');
        $optionsFromIni = new RedisClusterOptionsFromIni();
        $options        = new RedisClusterOptions([
            'seeds' => $optionsFromIni->seeds($name),
        ]);

        $storage = new RedisCluster($options);
        $this->assertTrue($storage->flush());
    }

    public function testWillHandleIntegratedSerializerInformation(): void
    {
        $storage = $this->storage;
        self::assertInstanceOf(StorageInterface::class, $storage);
        $this->removeSerializer($storage);

        $options = $storage->getOptions();
        $options->setLibOptions([
            RedisClusterFromExtension::OPT_SERIALIZER => RedisClusterFromExtension::SERIALIZER_PHP,
        ]);

        $capabilities = $storage->getCapabilities();
        $dataTypes    = $capabilities->getSupportedDatatypes();
        $this->assertEquals([
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

    private function removeSerializer(AbstractAdapter $storage): void
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
            RedisClusterFromExtension::OPT_SERIALIZER => RedisClusterFromExtension::SERIALIZER_NONE,
        ]);

        $capabilities = $storage->getCapabilities();
        $dataTypes    = $capabilities->getSupportedDatatypes();
        $this->assertEquals([
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
            RedisClusterFromExtension::SERIALIZER_PHP,
            false
        );
        // Clear storage before executing tests.
        $this->storage->flush();

        parent::setUp();
    }

    /**
     * Remove the property cache as we do want to create a new instance for the next test.
     */
    protected function tearDown(): void
    {
        $this->storage = null;
        parent::tearDown();
    }

    /**
     * @dataProvider getCommonAdapterNamesProvider
     */
    public function testAdapterPluginManagerWithCommonNames(string $commonAdapterName): void
    {
        self::markTestSkipped('RedisCluster is available by static factory.');
    }

    /**
     * @psalm-return array<array-key,list<non-empty-string>>
     */
    public function getCommonAdapterNamesProvider(): array
    {
        return [['dummy']];
    }

    public function testOptionsFluentInterface(): void
    {
        self::markTestSkipped('Redis cluster specific options do not provide fluent interface!');
    }
}
