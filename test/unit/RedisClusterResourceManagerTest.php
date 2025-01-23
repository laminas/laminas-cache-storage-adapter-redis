<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Adapter\RedisClusterResourceManager;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Serializer\AdapterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Redis;
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
            ->expects(self::once())
            ->method('getOptions')
            ->willReturn($options);

        $adapter
            ->expects($this->never())
            ->method('getPluginRegistry');

        self::assertTrue($manager->hasSerializationSupport($adapter));
    }

    public function testCanDetectSerializationSupportFromSerializerPlugin(): void
    {
        $registry = $this->createMock(SplObjectStorage::class);
        $registry
            ->expects($this->any())
            ->method('current')
            ->willReturn(new Serializer(new AdapterPluginManager(new ServiceManager())));

        $registry
            ->expects($this->once())
            ->method('valid')
            ->willReturn(true);

        $manager = new RedisClusterResourceManager(new RedisClusterOptions([
            'name' => uniqid('', true),
        ]));
        $adapter = $this->createMock(AbstractAdapter::class);
        $adapter
            ->expects(self::once())
            ->method('getOptions')
            ->willReturn(new RedisClusterOptions(['name' => 'foo']));

        $adapter
            ->expects($this->once())
            ->method('getPluginRegistry')
            ->willReturn($registry);

        self::assertTrue($manager->hasSerializationSupport($adapter));
    }

    /**
     * @psalm-return array<string,array{0:RedisClusterOptions}>
     */
    public static function serializationSupportOptionsProvider(): array
    {
        return [
            'php-serialize'      => [
                new RedisClusterOptions([
                    'name'        => uniqid('', true),
                    'lib_options' => [
                        Redis::OPT_SERIALIZER => Redis::SERIALIZER_PHP,
                    ],
                ]),
            ],
            'igbinary-serialize' => [
                new RedisClusterOptions([
                    'name'        => uniqid('', true),
                    'lib_options' => [
                        Redis::OPT_SERIALIZER => Redis::SERIALIZER_IGBINARY,
                    ],
                ]),
            ],
        ];
    }
}
