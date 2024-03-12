<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Generator;
use InvalidArgumentException;
use Laminas\Cache\Storage\Adapter\AdapterOptions;
use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Redis as RedisFromExtension;
use ReflectionClass;

use function assert;
use function constant;
use function defined;
use function is_int;
use function sprintf;
use function str_starts_with;

/**
 * @template-extends AbstractAdapterOptionsTest<RedisClusterOptions>
 */
final class RedisClusterOptionsTest extends AbstractAdapterOptionsTest
{
    protected function createAdapterOptions(): AdapterOptions
    {
        return new RedisClusterOptions(['seeds' => ['localhost']]);
    }

    public function testCanHandleOptionsWithNodename(): void
    {
        $options = new RedisClusterOptions([
            'name'          => 'foo',
            'timeout'       => 1.0,
            'read_timeout'  => 2.0,
            'persistent'    => false,
            'redis_version' => '1.0',
            'password'      => 'secret',
            'ssl_context'   => ['verify_peer' => false],
        ]);

        $this->assertEquals('foo', $options->getName());
        $this->assertEquals(1.0, $options->getTimeout());
        $this->assertEquals(2.0, $options->getReadTimeout());
        $this->assertEquals(false, $options->isPersistent());
        $this->assertEquals('1.0', $options->getRedisVersion());
        $this->assertEquals('secret', $options->getPassword());
        $this->assertEquals(['verify_peer' => false], $options->getSslContext());
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
            'ssl_context'   => ['verify_peer' => false],
        ]);

        $this->assertEquals(['localhost:1234'], $options->getSeeds());
        $this->assertEquals(1.0, $options->getTimeout());
        $this->assertEquals(2.0, $options->getReadTimeout());
        $this->assertEquals(false, $options->isPersistent());
        $this->assertEquals('1.0', $options->getRedisVersion());
        $this->assertEquals('secret', $options->getPassword());
        $this->assertEquals(['verify_peer' => false], $options->getSslContext());
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

    /**
     * @psalm-param non-empty-string $constant
     * @psalm-param positive-int    $constantValue
     * @dataProvider redisClusterOptionConstants
     */
    public function testOptionConstantsMatchingExtensionImplementation(string $constant, int $constantValue): void
    {
        $constantInOptions = sprintf('%s::%s', RedisClusterOptions::class, $constant);

        if (! defined($constantInOptions)) {
            self::markTestSkipped(sprintf(
                'Constant "%s" with value "%d" is not defined.',
                $constantInOptions,
                $constantValue
            ));
        }

        $constantValueInOptions = constant($constantInOptions);
        self::assertIsInt($constantValueInOptions);
        self::assertEquals(
            $constantValue,
            $constantValueInOptions,
            sprintf(
                'Constant "%s" diverged from ext-redis. Expected "%d", "%d" declared.',
                $constant,
                $constantValue,
                $constantValueInOptions
            )
        );
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:non-empty-string,1:positive-int}>
     */
    public function redisClusterOptionConstants(): Generator
    {
        $reflection = new ReflectionClass(RedisFromExtension::class);

        foreach ($reflection->getConstants() as $constant => $constantValue) {
            if (! str_starts_with($constant, 'OPT_')) {
                continue;
            }

            assert(is_int($constantValue) && $constantValue > 0);
            yield $constant => [$constant, $constantValue];
        }
    }

    /**
     * @psalm-param non-empty-string $constant
     * @psalm-param positive-int $constantValue
     * @dataProvider declaredLibraryOptionConstants
     */
    public function testLibraryOptionsConstantContainsAllDeclaredConstants(string $constant, int $constantValue): void
    {
        self::assertContains(
            $constantValue,
            RedisClusterOptions::LIBRARY_OPTIONS,
            sprintf(
                'Missing constant "%s" in %s::LIBRARY_OPTIONS',
                $constant,
                RedisClusterOptions::class
            )
        );
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:non-empty-string,1:positive-int}>
     */
    public function declaredLibraryOptionConstants(): Generator
    {
        $reflection = new ReflectionClass(RedisClusterOptions::class);

        foreach ($reflection->getConstants() as $constant => $constantValue) {
            if (! str_starts_with($constant, 'OPT_')) {
                continue;
            }

            assert(is_int($constantValue) && $constantValue > 0);
            yield $constant => [$constant, $constantValue];
        }
    }
}
