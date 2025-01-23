<?php

declare(strict_types=1);

namespace LaminasBench\Cache;

use Laminas\Cache\Storage\Adapter\Benchmark\AbstractStorageAdapterBenchmark;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Redis;

/**
 * @template-extends AbstractStorageAdapterBenchmark<RedisClusterOptions>
 */
#[Revs(100)]
#[Iterations(10)]
#[Warmup(1)]
class RedisClusterWithIgbinarySerializerStorageAdapterBench extends AbstractStorageAdapterBenchmark
{
    use RedisClusterStorageCreationTrait;

    public function __construct()
    {
        parent::__construct($this->createRedisClusterStorage(Redis::SERIALIZER_IGBINARY, false));
    }
}
