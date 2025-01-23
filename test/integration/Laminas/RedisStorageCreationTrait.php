<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Serializer\AdapterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Redis as RedisFromExtension;

trait RedisStorageCreationTrait
{
    use RedisConfigurationFromEnvironmentTrait;

    /**
     * @param RedisFromExtension::SERIALIZER_* $serializerOption
     */
    private function createRedisStorage(int $serializerOption, bool $serializerPlugin): Redis
    {
        $host    = $this->host();
        $port    = $this->port();
        $options = [];

        if ($host && $port) {
            $options['server'] = [$host, $port];
        } elseif ($host) {
            $options['server'] = [$host];
        }

        $options['database'] = $this->database();

        $password = $this->password();
        if ($password) {
            $options['password'] = $password;
        }

        $options['lib_options'] = [RedisFromExtension::OPT_SERIALIZER => $serializerOption];

        $storage = new Redis(new RedisOptions($options));
        if ($serializerOption === RedisFromExtension::SERIALIZER_NONE && $serializerPlugin) {
            $storage->addPlugin(new Serializer(new AdapterPluginManager(new ServiceManager())));
        }

        return $storage;
    }
}
