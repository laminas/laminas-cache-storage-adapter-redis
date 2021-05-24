<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use InvalidArgumentException;
use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use PHPUnit\Framework\TestCase;

final class RedisClusterOptionsTest extends TestCase
{
    public function testCanHandleOptionsWithNodename(): void
    {
        $options = new RedisClusterOptions([
            'nodename'      => 'foo',
            'timeout'       => 1.0,
            'read_timeout'  => 2.0,
            'persistent'    => false,
            'redis_version' => '1.0',
        ]);

        $this->assertEquals($options->nodename(), 'foo');
        $this->assertEquals($options->timeout(), 1.0);
        $this->assertEquals($options->readTimeout(), 2.0);
        $this->assertEquals($options->persistent(), false);
        $this->assertEquals($options->redisVersion(), '1.0');
    }

    public function testCanHandleOptionsWithSeeds(): void
    {
        $options = new RedisClusterOptions([
            'seeds'         => ['localhost:1234'],
            'timeout'       => 1.0,
            'read_timeout'  => 2.0,
            'persistent'    => false,
            'redis_version' => '1.0',
        ]);

        $this->assertEquals($options->seeds(), ['localhost:1234']);
        $this->assertEquals($options->timeout(), 1.0);
        $this->assertEquals($options->readTimeout(), 2.0);
        $this->assertEquals($options->persistent(), false);
        $this->assertEquals($options->redisVersion(), '1.0');
    }

    public function testWillDetectSeedsAndNodenameConfiguration(): void
    {
        $this->expectException(InvalidRedisClusterConfigurationException::class);
        $this->expectExceptionMessage('Please provide either `nodename` or `seeds` configuration, not both.');
        new RedisClusterOptions([
            'seeds'    => ['localhost:1234'],
            'nodename' => 'foo',
        ]);
    }

    public function testWillValidateVersionFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RedisClusterOptions([
            'redis_version' => 'foo',
        ]);
    }

    public function testWillValidateEmptyVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RedisClusterOptions([
            'redis_version' => '',
        ]);
    }

    public function testWillDetectMissingRequiredValues(): void
    {
        $this->expectException(InvalidRedisClusterConfigurationException::class);
        $this->expectExceptionMessage('Missing either `nodename` or `seeds`.');
        new RedisClusterOptions();
    }
}
