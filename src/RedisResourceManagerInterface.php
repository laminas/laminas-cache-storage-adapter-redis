<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\PluginCapableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Redis as RedisFromExtension;

interface RedisResourceManagerInterface
{
    public function getResource(): RedisFromExtension;

    /**
     * @param StorageInterface<RedisOptions>&PluginCapableInterface $adapter
     */
    public function hasSerializationSupport(StorageInterface&PluginCapableInterface $adapter): bool;
}
