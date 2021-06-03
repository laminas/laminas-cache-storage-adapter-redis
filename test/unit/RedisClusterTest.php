<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\RedisCluster;
use Laminas\Cache\Storage\Adapter\RedisClusterResourceManagerInterface;
use PHPUnit\Framework\TestCase;

final class RedisClusterTest extends TestCase
{
    public function testCanDetectCapabilitiesWithSerializationSupport(): void
    {
        $resourceManager = $this->createMock(RedisClusterResourceManagerInterface::class);

        $adapter = new RedisCluster([
            'name' => 'bar',
        ]);
        /** @psalm-suppress InternalMethod */
        $adapter->setResourceManager($resourceManager);

        $resourceManager
            ->expects($this->once())
            ->method('hasSerializationSupport')
            ->with($adapter)
            ->willReturn(true);

        $resourceManager
            ->expects($this->once())
            ->method('getVersion')
            ->willReturn('5.0.0');

        $capabilities = $adapter->getCapabilities();
        $datatypes    = $capabilities->getSupportedDatatypes();
        $this->assertEquals([
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
        $resourceManager = $this->createMock(RedisClusterResourceManagerInterface::class);

        $adapter = new RedisCluster([
            'name' => 'bar',
        ]);
        /** @psalm-suppress InternalMethod */
        $adapter->setResourceManager($resourceManager);

        $resourceManager
            ->expects($this->once())
            ->method('hasSerializationSupport')
            ->with($adapter)
            ->willReturn(false);

        $resourceManager
            ->expects($this->once())
            ->method('getVersion')
            ->willReturn('5.0.0');

        $capabilities = $adapter->getCapabilities();
        $datatypes    = $capabilities->getSupportedDatatypes();
        $this->assertEquals([
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
