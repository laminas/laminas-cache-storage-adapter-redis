<?php

declare(strict_types=1);

namespace LaminasBench\Cache;

use Laminas\Cache\Storage\Adapter\Benchmark\AbstractStorageAdapterBenchmark;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisClusterStorageCreationTrait;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;
use RedisCluster;

/**
 * @Revs(100)
 * @Iterations(10)
 * @Warmup(1)
 */
class RedisClusterWithPhpSerializerStorageAdapterBench extends AbstractStorageAdapterBenchmark
{
    use RedisClusterStorageCreationTrait;

    public function __construct()
    {
        parent::__construct($this->createRedisClusterStorage(
            RedisCluster::SERIALIZER_PHP,
            false
        ));
    }
}
