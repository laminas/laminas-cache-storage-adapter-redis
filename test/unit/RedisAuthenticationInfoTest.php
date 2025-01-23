<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\RedisAuthenticationInfo;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_filter;

final class RedisAuthenticationInfoTest extends TestCase
{
    private const DUMMY_USER     = 'user';
    private const DUMMY_PASSWORD = 'password';

    #[DataProvider('authenticationInfo')]
    public function testUserAndPasswordCombinationsForRedis(
        ?string $user,
        ?string $password,
        array|null $expectedAuthentication
    ): void {
        $options = new RedisOptions(array_filter(
            ['user' => $user, 'password' => $password],
            fn($element) => $element !== null
        ));
        /** @psalm-suppress InternalMethod,InternalClass We are explicitly testing an internal method here */
        $actualAuthentication = RedisAuthenticationInfo::fromOptions($options);
        /** @psalm-suppress InternalMethod We are explicitly testing an internal method here */
        self::assertEquals($expectedAuthentication, $actualAuthentication?->toRedisAuthInfo());
    }

    #[DataProvider('authenticationInfo')]
    public function testUserAndPasswordCombinationsForRedisCluster(
        ?string $user,
        ?string $password,
        array|null $expectedAuthentication
    ): void {
        $options = new RedisClusterOptions(array_filter(
            ['user' => $user, 'password' => $password, 'name' => 'test'],
            fn($element) => $element !== null
        ));
        /** @psalm-suppress InternalMethod,InternalClass We are explicitly testing an internal method here */
        $actualAuthentication = RedisAuthenticationInfo::fromOptions($options);
        /** @psalm-suppress InternalMethod We are explicitly testing an internal method here */
        self::assertEquals($expectedAuthentication, $actualAuthentication?->toRedisAuthInfo());
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod PHPUnit psalm plugin does not yet support attributes
     * @psalm-return non-empty-array<non-empty-string, array{
     *  0:string|null,
     *  1:string|null,
     *  2:array{0: non-empty-string, 1?: non-empty-string}|null
     * }>
     */
    public static function authenticationInfo(): array
    {
        return [
            'user and password'                                  => [
                self::DUMMY_USER,
                self::DUMMY_PASSWORD,
                [self::DUMMY_USER, self::DUMMY_PASSWORD],
            ],
            'only password (user is empty string)'               => [
                '',
                self::DUMMY_PASSWORD,
                [self::DUMMY_PASSWORD],
            ],
            'only password (user is null)'                       => [
                null,
                self::DUMMY_PASSWORD,
                [self::DUMMY_PASSWORD],
            ],
            'no authentication provided (empty strings)'         => [
                '',
                '',
                null,
            ],
            'no authentication provided (null values)'           => [
                null,
                null,
                null,
            ],
            'user without a password (password is empty string)' => [
                self::DUMMY_USER,
                '',
                null,
            ],
            'user without a password (password is null)'         => [
                self::DUMMY_USER,
                null,
                null,
            ],
        ];
    }
}
