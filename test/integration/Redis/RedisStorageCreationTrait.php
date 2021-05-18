<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Redis;

use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Plugin\Serializer;
use Redis as RedisFromExtension;

trait RedisStorageCreationTrait
{
    use RedisConfigurationFromEnvironmentTrait;

    private function createRedisStorage(int $serializerOption, bool $serializerPlugin): Redis
    {
        $options = ['resource_id' => self::class];

        $host = $this->host();
        $port = $this->port();

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

        $storage = new Redis(new RedisOptions($options));
        if ($serializerOption === RedisFromExtension::SERIALIZER_NONE && $serializerPlugin) {
            $storage->addPlugin(new Serializer());
        }

        return $storage;
    }
}
