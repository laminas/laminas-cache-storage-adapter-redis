<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Redis;

use Laminas\Cache\Storage\AdapterPluginManager;
use Laminas\ServiceManager\ServiceManager;

/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
final class ConfigProvider
{
    /**
     * @return array{dependencies:ServiceManagerConfiguration}
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getServiceDependencies(),
        ];
    }

    /**
     * @return ServiceManagerConfiguration
     */
    public function getServiceDependencies(): array
    {
        return [
            'delegators' => [
                AdapterPluginManager::class => [
                    AdapterPluginManagerDelegatorFactory::class,
                ],
            ],
        ];
    }
}
