<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Redis;

final class Module
{
    public function getConfig(): array
    {
        $config                    = (new ConfigProvider())();
        $dependencies              = $config['dependencies'] ?? [];
        $config['service_manager'] = $dependencies;
        unset($config['dependencies']);

        return $config;
    }
}
