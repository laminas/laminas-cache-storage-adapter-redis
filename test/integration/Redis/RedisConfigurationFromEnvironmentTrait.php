<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Redis;

use function getenv;

trait RedisConfigurationFromEnvironmentTrait
{
    private function host(): string
    {
        $host = getenv('TESTS_LAMINAS_CACHE_REDIS_HOST');
        if ($host === false) {
            return '';
        }

        return $host;
    }

    private function port(): int
    {
        $port = getenv('TESTS_LAMINAS_CACHE_REDIS_PORT');
        if ($port === false) {
            return 0;
        }

        return (int) $port;
    }

    private function database(): int
    {
        $database = getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE');
        if ($database === false) {
            return 0;
        }

        return (int) $database;
    }

    private function password(): string
    {
        $password = getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD');
        if ($password === false) {
            return '';
        }

        return $password;
    }

    private function nodename(): string
    {
        $nodename = getenv('TESTS_LAMINAS_CACHE_REDIS_CLUSTER_NODENAME');
        if ($nodename === false) {
            return '';
        }

        return $nodename;
    }
}
