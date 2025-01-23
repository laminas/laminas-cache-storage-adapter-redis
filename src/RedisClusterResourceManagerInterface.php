<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\PluginCapableInterface;
use Laminas\Cache\Storage\StorageInterface;
use RedisCluster as RedisClusterFromExtension;

interface RedisClusterResourceManagerInterface
{
    public function getResource(): RedisClusterFromExtension;

    /**
     * @param StorageInterface<RedisClusterOptions>&PluginCapableInterface $adapter
     */
    public function hasSerializationSupport(PluginCapableInterface&StorageInterface $adapter): bool;
}
