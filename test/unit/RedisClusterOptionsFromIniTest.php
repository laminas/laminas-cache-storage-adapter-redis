<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisConfigurationException;
use Laminas\Cache\Storage\Adapter\RedisClusterOptionsFromIni;
use PHPUnit\Framework\TestCase;

use function ini_get;
use function ini_set;

final class RedisClusterOptionsFromIniTest extends TestCase
{
    /** @var string */
    private $seedsConfigurationFromIni;

    public function testWillThrowExceptionOnMissingSeedsConfiguration(): void
    {
        $this->expectException(InvalidRedisConfigurationException::class);
        new RedisClusterOptionsFromIni();
    }

    /**
     * @dataProvider seedsByNodenameProvider
     */
    public function testWillDetectSeedsByNodename(string $nodename, string $config, array $expected): void
    {
        ini_set('redis.clusters.seeds', $config);
        $options = new RedisClusterOptionsFromIni();
        $seeds   = $options->seeds($nodename);
        $this->assertEquals($expected, $seeds);
    }

    public function testWillThrowExceptionOnMissingNodenameInSeeds(): void
    {
        ini_set('redis.clusters.seeds', 'foo[]=bar:123');
        $options = new RedisClusterOptionsFromIni();
        $this->expectException(InvalidRedisConfigurationException::class);
        $options->seeds('bar');
    }

    /**
     * @psalm-return non-empty-array<non-empty-string,array{0:non-empty-string,1:non-empty-string,2:non-empty-list<non-empty-string>}>
     */
    public function seedsByNodenameProvider(): array
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
