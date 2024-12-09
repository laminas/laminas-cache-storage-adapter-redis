<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

/**
 * @internal
 */
final class RedisAuthenticationInfo
{
    /**
     * @param non-empty-string|null $username
     * @param non-empty-string $password
     */
    private function __construct(
        private readonly string|null $username,
        private readonly string $password,
    ) {
    }

    public static function fromOptions(RedisClusterOptions|RedisOptions $options): self|null
    {
        $username = $options->getUser();
        $password = $options->getPassword();

        if ($password === null || $password === '') {
            return null;
        }

        if ($username === null || $username === '') {
            return new self(null, $password);
        }

        return new self($username, $password);
    }

    /**
     * @see https://github.com/phpredis/phpredis/blob/4cd3f59356582a65aec1cceed44741bd5d161d9e/library.c#L4382
     *
     * @return array{0:non-empty-string,1?:non-empty-string}
     */
    public function toRedisAuthInfo(): array
    {
        if ($this->username === null) {
            return [$this->password];
        }

        return [$this->username, $this->password];
    }
}
