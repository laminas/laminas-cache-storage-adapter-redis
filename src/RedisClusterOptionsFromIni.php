<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use InvalidArgumentException;
use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Webmozart\Assert\Assert;

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
    /** @var array<non-empty-string,non-empty-list<non-empty-string>> */
    private array $seedsByName;

    /** @var array<non-empty-string,float> */
    private array $timeoutByName;

    /** @var array<non-empty-string,float> */
    private array $readTimeoutByName;

    /** @var array<non-empty-string,string> */
    private array $passwordByName;

    public function __construct()
    {
        $seedsConfiguration = ini_get('redis.clusters.seeds');
        if ($seedsConfiguration === false) {
            $seedsConfiguration = '';
        }

        if ($seedsConfiguration === '') {
            throw InvalidRedisClusterConfigurationException::fromMissingSeedsConfiguration();
        }

        $seedsByName = [];
        parse_str($seedsConfiguration, $parsedSeedsByName);
        try {
            foreach ($parsedSeedsByName as $name => $seeds) {
                assert(is_string($name) && $name !== '');
                Assert::isNonEmptyList($seeds);
                Assert::allStringNotEmpty($seeds);
                $seedsByName[$name] = $seeds;
            }
        } catch (InvalidArgumentException) {
            throw InvalidRedisClusterConfigurationException::fromInvalidSeedsConfiguration($seedsConfiguration);
        }

        $this->seedsByName = $seedsByName;

        $timeoutConfiguration = ini_get('redis.clusters.timeout');
        if ($timeoutConfiguration === false || $timeoutConfiguration === '0') {
            $timeoutConfiguration = '';
        }

        $timeoutByName = [];
        if ($timeoutConfiguration !== '') {
            parse_str($timeoutConfiguration, $parsedTimeoutByName);
            foreach ($parsedTimeoutByName as $name => $timeout) {
                assert(is_string($name) && $name !== '' && is_numeric($timeout));
                $timeoutByName[$name] = (float) $timeout;
            }
        }

        $this->timeoutByName = $timeoutByName;

        $readTimeoutConfiguration = ini_get('redis.clusters.read_timeout');
        if ($readTimeoutConfiguration === false || $readTimeoutConfiguration === '0') {
            $readTimeoutConfiguration = '';
        }

        $readTimeoutByName = [];
        if ($readTimeoutConfiguration !== '') {
            parse_str($readTimeoutConfiguration, $parsedReadTimeoutByName);
            foreach ($parsedReadTimeoutByName as $name => $readTimeout) {
                assert(is_string($name) && $name !== '' && is_numeric($readTimeout));
                $readTimeoutByName[$name] = (float) $readTimeout;
            }
        }

        $this->readTimeoutByName = $readTimeoutByName;

        $authenticationConfiguration = ini_get('redis.clusters.auth');
        if ($authenticationConfiguration === false) {
            $authenticationConfiguration = '';
        }

        $passwordByName = [];
        if ($authenticationConfiguration !== '') {
            parse_str($authenticationConfiguration, $parsedAuthenticationByName);
            foreach ($parsedAuthenticationByName as $name => $password) {
                assert(is_string($name) && $name !== '' && is_string($password));
                $passwordByName[$name] = $password;
            }
        }

        $this->passwordByName = $passwordByName;
    }

    /**
     * @param non-empty-string $name
     * @return non-empty-list<non-empty-string>
     */
    public function getSeeds(string $name): array
    {
        $seeds = $this->seedsByName[$name] ?? [];
        if (! $seeds) {
            throw InvalidRedisClusterConfigurationException::fromMissingSeedsForNamedConfiguration($name);
        }

        return $seeds;
    }

    /**
     * @psalm-param non-empty-string $name
     */
    public function getTimeout(string $name, float $fallback): float
    {
        return $this->timeoutByName[$name] ?? $fallback;
    }

    /**
     * @psalm-param non-empty-string $name
     */
    public function getReadTimeout(string $name, float $fallback): float
    {
        return $this->readTimeoutByName[$name] ?? $fallback;
    }

    /**
     * @psalm-param non-empty-string $name
     */
    public function getPasswordByName(string $name, string $fallback): string
    {
        return $this->passwordByName[$name] ?? $fallback;
    }
}
