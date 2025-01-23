<?php

use Laminas\Cache\Storage\Adapter\SslContext;

/**
 * Created RedisOptions based on source code:
 * @link https://github.com/phpredis/phpredis/blob/b808cc60ed09bd5f0efc22508c43db90a3e1219e/redis.stub.php
 *
 * @psalm-import-type SSLContextArrayShape from SslContext
 * @psalm-type RedisOptions = array{
 *   host: non-empty-string,
 *   port?: int<1,65536>,
 *   readTimeout?: float,
 *   connectTimeout?: float,
 *   retryInterval?: non-negative-int,
 *   persistent?: bool|non-empty-string,
 *   auth?: null|array{0:non-empty-string,1?:non-empty-string}|non-empty-string,
 *   ssl?: SSLContextArrayShape,
 *   backoff?: array{
 *      algorithm?: Redis::BACKOFF_ALGORITHM_*,
 *      base?: non-negative-int,
 *      cap?: non-negative-int
 *   }
 * }
 */
class Redis
{
    /**
     * @param RedisOptions|null} $options
     */
    public function __construct(array|null $options = null)
    {
    }
}