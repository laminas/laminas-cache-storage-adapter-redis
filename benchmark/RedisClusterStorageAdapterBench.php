<?php

declare(strict_types=1);

namespace LaminasBench\Cache;

use Laminas\Cache\Storage\Adapter\Benchmark\AbstractStorageAdapterBenchmark;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use RedisCluster;

/**
 * @Revs(100)
 * @Iterations(10)
 * @Warmup(1)
 */
class RedisClusterStorageAdapterBench extends AbstractStorageAdapterBenchmark
{
    use RedisClusterStorageCreationTrait;

    public function __construct()
    {
        parent::__construct($this->createRedisClusterStorage(
            RedisCluster::SERIALIZER_NONE,
            true
        ));
    }
}
