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
            'name'          => 'foo',
            'timeout'       => 1.0,
            'read_timeout'  => 2.0,
            'persistent'    => false,
            'redis_version' => '1.0',
            'password'      => 'secret',
        ]);

        $this->assertEquals('foo', $options->getName());
        $this->assertEquals(1.0, $options->getTimeout());
        $this->assertEquals(2.0, $options->getReadTimeout());
        $this->assertEquals(false, $options->isPersistent());
        $this->assertEquals('1.0', $options->getRedisVersion());
        $this->assertEquals('secret', $options->getPassword());
    }

    public function testCanHandleOptionsWithSeeds(): void
    {
        $options = new RedisClusterOptions([
            'seeds'         => ['localhost:1234'],
            'timeout'       => 1.0,
            'read_timeout'  => 2.0,
            'persistent'    => false,
            'redis_version' => '1.0',
            'password'      => 'secret',
        ]);

        $this->assertEquals(['localhost:1234'], $options->getSeeds());
        $this->assertEquals(1.0, $options->getTimeout());
        $this->assertEquals(2.0, $options->getReadTimeout());
        $this->assertEquals(false, $options->isPersistent());
        $this->assertEquals('1.0', $options->getRedisVersion());
        $this->assertEquals('secret', $options->getPassword());
    }

    public function testWillDetectSeedsAndNodenameConfiguration(): void
    {
        $this->expectException(InvalidRedisClusterConfigurationException::class);
        $this->expectExceptionMessage('Please provide either `name` or `seeds` configuration, not both.');
        new RedisClusterOptions([
            'seeds' => ['localhost:1234'],
            'name'  => 'foo',
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
        $this->expectExceptionMessage('Missing either `name` or `seeds`.');
        new RedisClusterOptions();
    }
}
