<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\PluginCapableInterface;
use RedisCluster as RedisClusterFromExtension;

interface RedisClusterResourceManagerInterface
{
    public function getVersion(): string;

    public function getResource(): RedisClusterFromExtension;

    /**
     * @return mixed
     */
    public function getLibOption(int $option);

    public function hasSerializationSupport(PluginCapableInterface $adapter): bool;
}
