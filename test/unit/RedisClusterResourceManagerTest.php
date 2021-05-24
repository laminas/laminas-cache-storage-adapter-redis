<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Adapter\RedisClusterResourceManager;
use Laminas\Cache\Storage\Plugin\Serializer;
use PHPUnit\Framework\TestCase;
use RedisCluster;
use SplObjectStorage;

use function uniqid;

final class RedisClusterResourceManagerTest extends TestCase
{
    /**
     * @dataProvider serializationSupportOptionsProvider
     */
    public function testCanDetectSerializationSupportFromOptions(RedisClusterOptions $options): void
    {
        $manager = new RedisClusterResourceManager($options);
        $adapter = $this->createMock(AbstractAdapter::class);
        $adapter
            ->expects($this->never())
            ->method('getPluginRegistry');

        $this->assertTrue($manager->hasSerializationSupport($adapter));
    }

    public function testCanDetectSerializationSupportFromSerializerPlugin(): void
    {
        $registry = $this->createMock(SplObjectStorage::class);
        $registry
            ->expects($this->any())
            ->method('current')
            ->willReturn(new Serializer());

        $registry
            ->expects($this->once())
            ->method('valid')
            ->willReturn(true);

        $manager = new RedisClusterResourceManager(new RedisClusterOptions([
            'name' => uniqid('', true),
        ]));
        $adapter = $this->createMock(AbstractAdapter::class);
        $adapter
            ->expects($this->once())
            ->method('getPluginRegistry')
            ->willReturn($registry);

        $this->assertTrue($manager->hasSerializationSupport($adapter));
    }

    public function testWillReturnVersionFromOptions(): void
    {
        $manager = new RedisClusterResourceManager(new RedisClusterOptions([
            'name'          => uniqid('', true),
            'redis_version' => '1.0.0',
        ]));

        $version = $manager->getVersion();
        $this->assertEquals('1.0.0', $version);
    }

    /**
     * @psalm-return array<string,array{0:RedisClusterOptions}>
     */
    public function serializationSupportOptionsProvider(): array
    {
        return [
            'php-serialize'      => [
                new RedisClusterOptions([
                    'name'        => uniqid('', true),
                    'lib_options' => [
                        RedisCluster::OPT_SERIALIZER => RedisCluster::SERIALIZER_PHP,
                    ],
                ]),
            ],
            'igbinary-serialize' => [
                new RedisClusterOptions([
                    'name'        => uniqid('', true),
                    'lib_options' => [
                        RedisCluster::OPT_SERIALIZER => RedisCluster::SERIALIZER_IGBINARY,
                    ],
                ]),
            ],
        ];
    }
}
