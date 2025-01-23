<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Adapter\RedisResourceManagerInterface;
use PHPUnit\Framework\TestCase;

final class RedisTest extends TestCase
{
    public function testWillReturnVersionFromOptions(): void
    {
        $adapter = new Redis(new RedisOptions([
            'redis_version' => '1.0.0',
        ]));

        $version = $adapter->getRedisVersion();
        self::assertEquals('1.0.0', $version);
    }

    public function testCanDetectCapabilitiesWithSerializationSupport(): void
    {
        $resourceManager = $this->createMock(RedisResourceManagerInterface::class);

        $adapter = new Redis(new RedisOptions([
            'redis_version' => '5.0.0',
        ]));

        $adapter->setResourceManager($resourceManager);

        $resourceManager
            ->expects($this->once())
            ->method('hasSerializationSupport')
            ->with($adapter)
            ->willReturn(true);

        $capabilities = $adapter->getCapabilities();
        $datatypes    = $capabilities->supportedDataTypes;
        self::assertEquals([
            'NULL'     => true,
            'boolean'  => true,
            'integer'  => true,
            'double'   => true,
            'string'   => true,
            'array'    => 'array',
            'object'   => 'object',
            'resource' => false,
        ], $datatypes);
    }

    public function testCanDetectCapabilitiesWithoutSerializationSupport(): void
    {
        $resourceManager = $this->createMock(RedisResourceManagerInterface::class);

        $adapter = new Redis(new RedisOptions([
            'redis_version' => '5.0.0',
        ]));

        $adapter->setResourceManager($resourceManager);

        $resourceManager
            ->expects($this->once())
            ->method('hasSerializationSupport')
            ->with($adapter)
            ->willReturn(false);

        $capabilities = $adapter->getCapabilities();
        $datatypes    = $capabilities->supportedDataTypes;
        self::assertEquals([
            'NULL'     => 'string',
            'boolean'  => 'string',
            'integer'  => 'string',
            'double'   => 'string',
            'string'   => true,
            'array'    => false,
            'object'   => false,
            'resource' => false,
        ], $datatypes);
    }
}
