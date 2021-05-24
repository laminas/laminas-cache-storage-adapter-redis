<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;

use function assert;
use function ini_get;
use function is_numeric;
use function is_string;
use function parse_str;

/**
 * @link https://github.com/phpredis/phpredis/blob/e9ba9ff12e74c3483f2cb54b7fc9fb7250829a2a/cluster.markdown#loading-a-cluster-configuration-by-name
 */
final class RedisClusterOptionsFromIni
{
    /** @psalm-var array<non-empty-string,list<non-empty-string>> */
    private $seedsByNodename;

    /** @psalm-var array<non-empty-string,float> */
    private $timeoutByNodename;

    /** @psalm-var array<non-empty-string,float> */
    private $readTimeoutByNodename;

    public function __construct()
    {
        $seedsConfiguration = ini_get('redis.clusters.seeds');
        if (! is_string($seedsConfiguration)) {
            $seedsConfiguration = '';
        }

        if ($seedsConfiguration === '') {
            throw InvalidRedisClusterConfigurationException::fromMissingSeedsConfiguration();
        }

        $seedsByNodename = [];
        parse_str($seedsConfiguration, $seedsByNodename);
        /** @psalm-var non-empty-array<non-empty-string,list<non-empty-string>> $seedsByNodename */
        $this->seedsByNodename = $seedsByNodename;

        $timeoutConfiguration = ini_get('redis.clusters.timeout');
        if (! is_string($timeoutConfiguration)) {
            $timeoutConfiguration = '';
        }

        $timeoutByNodename = [];
        parse_str($timeoutConfiguration, $timeoutByNodename);
        foreach ($timeoutByNodename as $nodename => $timeout) {
            assert($nodename !== '' && is_numeric($timeout));
            $timeoutByNodename[$nodename] = (float) $timeout;
        }
        /** @psalm-var array<non-empty-string,float> $timeoutByNodename */
        $this->timeoutByNodename = $timeoutByNodename;

        $readTimeoutConfiguration = ini_get('redis.clusters.read_timeout');
        if (! is_string($readTimeoutConfiguration)) {
            $readTimeoutConfiguration = '';
        }

        $readTimeoutByNodename = [];
        parse_str($readTimeoutConfiguration, $readTimeoutByNodename);
        foreach ($readTimeoutByNodename as $nodename => $readTimeout) {
            assert($nodename !== '' && is_numeric($readTimeout));
            $readTimeoutByNodename[$nodename] = (float) $readTimeout;
        }

        /** @psalm-var array<non-empty-string,float> $readTimeoutByNodename */
        $this->readTimeoutByNodename = $readTimeoutByNodename;
    }

    /**
     * @return array<int,string>
     * @psalm-return list<non-empty-string>
     */
    public function seeds(string $nodename): array
    {
        $seeds = $this->seedsByNodename[$nodename] ?? [];
        if (! $seeds) {
            throw InvalidRedisClusterConfigurationException::forMissingSeedsForNodename($nodename);
        }

        return $seeds;
    }

    public function timeout(string $nodename, float $fallback): float
    {
        return $this->timeoutByNodename[$nodename] ?? $fallback;
    }

    public function readTimeout(string $nodename, float $fallback): float
    {
        return $this->readTimeoutByNodename[$nodename] ?? $fallback;
    }
}
