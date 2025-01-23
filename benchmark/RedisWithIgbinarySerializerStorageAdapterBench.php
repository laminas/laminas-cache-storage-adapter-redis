<?php

declare(strict_types=1);

namespace LaminasBench\Cache;

use Laminas\Cache\Storage\Adapter\Benchmark\AbstractStorageAdapterBenchmark;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Redis;

/**
 * @template-extends AbstractStorageAdapterBenchmark<RedisOptions>
 */
#[Revs(100)]
#[Iterations(10)]
#[Warmup(1)]
class RedisWithIgbinarySerializerStorageAdapterBench extends AbstractStorageAdapterBenchmark
{
    use RedisStorageCreationTrait;

    public function __construct()
    {
        parent::__construct($this->createRedisStorage(Redis::SERIALIZER_IGBINARY, false));
    }
}
