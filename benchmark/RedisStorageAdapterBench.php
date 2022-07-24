<?php

declare(strict_types=1);

namespace LaminasBench\Cache;

use Laminas\Cache\Storage\Adapter\Benchmark\AbstractStorageAdapterBenchmark;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;
use Redis;

/**
 * @Revs(100)
 * @Iterations(10)
 * @Warmup(1)
 */
class RedisStorageAdapterBench extends AbstractStorageAdapterBenchmark
{
    use RedisStorageCreationTrait;

    public function __construct()
    {
        parent::__construct($this->createRedisStorage(
            Redis::SERIALIZER_NONE,
            true
        ));
    }
}
