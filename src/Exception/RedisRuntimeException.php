<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Exception;

use Laminas\Cache\Exception\RuntimeException as LaminasCacheRuntimeException;
use Redis;
use RedisCluster;
use RedisClusterException;
use RedisException;
use Throwable;

final class RedisRuntimeException extends LaminasCacheRuntimeException
{
    private const INTERNAL_REDIS_ERROR = 'Something went wrong while interacting with redis cluster.';

    public static function fromClusterException(RedisClusterException $exception, RedisCluster $redis): self
    {
        $message = $redis->getLastError() ?? $exception->getMessage();

        return new self($message, $exception->getCode(), $exception);
    }

    public static function fromFailedConnection(Throwable $exception): self
    {
        return new self(
            'Could not establish connection',
            (int) $exception->getCode(),
            $exception
        );
    }

    public static function fromRedisException(RedisException $exception, Redis $redis): self
    {
        try {
            $message = $redis->getLastError() ?? $exception->getMessage();
        } catch (RedisException $exceptionThrownByGetLastErrorMethod) {
            $message = $exception->getMessage();
        }

        return new self($message, $exception->getCode(), $exception);
    }

    public static function fromInternalRedisError(RedisCluster|Redis $redis): self
    {
        try {
            $message = $redis->getLastError() ?? self::INTERNAL_REDIS_ERROR;
        } catch (RedisException) {
            $message = self::INTERNAL_REDIS_ERROR;
        }

        return new self($message);
    }
}
