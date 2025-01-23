<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Laminas\Cache\Storage\Adapter\RedisClusterOptionsFromIni;
use PHPUnit\Framework\TestCase;

use function ini_get;
use function ini_set;

final class RedisClusterOptionsFromIniTest extends TestCase
{
    private string $seedsConfigurationFromIni;

    public function testWillThrowExceptionOnMissingSeedsConfiguration(): void
    {
        $this->expectException(InvalidRedisClusterConfigurationException::class);
        new RedisClusterOptionsFromIni();
    }

    /**
     * @psalm-param non-empty-string $name
     * @dataProvider seedsByNameProvider
     */
    public function testWillDetectSeedsByName(string $name, string $config, array $expected): void
    {
        ini_set('redis.clusters.seeds', $config);
        $options = new RedisClusterOptionsFromIni();
        $seeds   = $options->getSeeds($name);
        self::assertEquals($expected, $seeds);
    }

    public function testWillThrowExceptionOnMissingNameInSeeds(): void
    {
        ini_set('redis.clusters.seeds', 'foo[]=bar:123');
        $options = new RedisClusterOptionsFromIni();
        $this->expectException(InvalidRedisClusterConfigurationException::class);
        $options->getSeeds('bar');
    }

    /**
     * @psalm-return non-empty-array<non-empty-string,array{0:non-empty-string,1:non-empty-string,2:non-empty-list<non-empty-string>}>
     */
    public static function seedsByNameProvider(): array
    {
        return [
            'simple'         => [
                'foo',
                'foo[]=localhost:1234',
                ['localhost:1234'],
            ],
            'multiple seeds' => [
                'bar',
                'bar[]=localhost:4321&bar[]=localhost:1234',
                ['localhost:4321', 'localhost:1234'],
            ],
            'multiple nodes' => [
                'baz',
                'foo[]=localhost:7000&foo[]=localhost=7001&baz[]=localhost:7002&baz[]=localhost:7003',
                ['localhost:7002', 'localhost:7003'],
            ],
        ];
    }

    public function testCanParseAllConfigurationsForName(): void
    {
        ini_set('redis.clusters.seeds', 'foo[]=bar');
        ini_set('redis.clusters.timeout', 'foo=1.0');
        ini_set('redis.clusters.read_timeout', 'foo=2.0');
        ini_set('redis.clusters.auth', 'foo=secret');
        $options = new RedisClusterOptionsFromIni();

        self::assertEquals(['bar'], $options->getSeeds('foo'));
        self::assertEquals(1.0, $options->getTimeout('foo', 0.0));
        self::assertEquals(2.0, $options->getReadTimeout('foo', 0.0));
        self::assertEquals('secret', $options->getPasswordByName('foo', ''));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedsConfigurationFromIni = ini_get('redis.clusters.seeds');
        ini_set('redis.clusters.seeds', '');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ini_set('redis.clusters.seeds', $this->seedsConfigurationFromIni);
    }
}
