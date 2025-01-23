<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Redis;

final class Metadata
{
    public const TTL_UNLIMITED = -1;

    /**
     * @param int<-1,max>|null $remainingTimeToLive Depending on the redis version, ttl might be `null`
     */
    public function __construct(
        public readonly int|null $remainingTimeToLive,
    ) {
    }
}
