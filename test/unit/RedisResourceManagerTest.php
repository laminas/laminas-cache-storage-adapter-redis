<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Adapter\RedisResourceManager;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Serializer\AdapterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use SplObjectStorage;

/**
 * @covers Laminas\Cache\Storage\Adapter\RedisResourceManager
 */
final class RedisResourceManagerTest extends TestCase
{
    #[DataProvider('serializationSupportOptionsProvider')]
    public function testCanDetectSerializationSupportFromOptions(RedisOptions $options): void
    {
        $manager = new RedisResourceManager($options);
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

        $manager = new RedisResourceManager(new RedisOptions([]));
        $adapter = $this->createMock(AbstractAdapter::class);
        $adapter
            ->expects(self::once())
            ->method('getOptions')
            ->willReturn(new RedisOptions());

        $adapter
            ->expects($this->once())
            ->method('getPluginRegistry')
            ->willReturn($registry);

        self::assertTrue($manager->hasSerializationSupport($adapter));
    }

    /**
     * @psalm-return array<string,array{0:RedisOptions}>
     */
    public static function serializationSupportOptionsProvider(): array
    {
        return [
            'php-serialize'      => [
                new RedisOptions([
                    'lib_options' => [
                        Redis::OPT_SERIALIZER => Redis::SERIALIZER_PHP,
                    ],
                ]),
            ],
            'igbinary-serialize' => [
                new RedisOptions([
                    'lib_options' => [
                        Redis::OPT_SERIALIZER => Redis::SERIALIZER_IGBINARY,
                    ],
                ]),
            ],
        ];
    }
}
