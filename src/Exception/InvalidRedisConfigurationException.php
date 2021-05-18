<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Exception;

use Laminas\Cache\Exception\InvalidArgumentException;

use function sprintf;

final class InvalidRedisConfigurationException extends InvalidArgumentException
{
    public static function fromMissingSeedsConfiguration(): self
    {
        return new self('Could not find `redis.clusters.seeds` entry in the php.ini file(s).');
    }

    public static function forMissingSeedsForNodename(string $nodename): self
    {
        return new self(sprintf(
            'Missing `%s` within the configured `redis.cluster.seeds` entry in the php.ini file(s).',
            $nodename
        ));
    }

    public static function fromMissingRequiredValues(): self
    {
        return new self('Missing either `nodename` or `seeds`.');
    }

    public static function nodenameAndSeedsProvided(): self
    {
        return new self('Please provide either `nodename` or `seeds` configuration, not both.');
    }

    public static function fromInvalidSeedsConfiguration(string $seed): self
    {
        return new self(
            sprintf(
                'Configured `seed` %s is invalid. Must be configured as "host:port" while separated by a colon.',
                $seed
            )
        );
    }
}