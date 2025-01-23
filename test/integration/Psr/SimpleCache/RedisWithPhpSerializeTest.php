<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\SimpleCache;

use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractSimpleCacheIntegrationTest;
use LaminasTest\Cache\Storage\Adapter\Laminas\RedisStorageCreationTrait;
use Redis;

final class RedisWithPhpSerializeTest extends AbstractSimpleCacheIntegrationTest
{
    use RedisStorageCreationTrait;

    protected function createStorage(): StorageInterface
    {
        return $this->createRedisStorage(Redis::SERIALIZER_PHP, true);
    }
}
