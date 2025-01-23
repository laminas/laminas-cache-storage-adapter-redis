<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\AdapterOptions;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Redis as RedisResource;

/**
 * @template-extends AbstractAdapterOptionsTest<RedisOptions>
 */
final class RedisOptionsTest extends AbstractAdapterOptionsTest
{
    public function testOptionsFluentInterface(): void
    {
        self::markTestSkipped('Redis cluster specific options do not provide fluent interface!');
    }

    protected function createAdapterOptions(): AdapterOptions
    {
        return new RedisOptions(['server' => ['host' => 'localhost']]);
    }

    public function testGetSetNamespace(): void
    {
        $namespace = 'testNamespace';
        $this->options->setNamespace($namespace);
        self::assertEquals($namespace, $this->options->getNamespace());
    }

    public function testGetSetNamespaceSeparator(): void
    {
        $separator = '/';
        $this->options->setNamespaceSeparator($separator);
        self::assertEquals($separator, $this->options->getNamespaceSeparator());
    }

    public function testGetSetPersistentId(): void
    {
        $persistentId = '1';
        $this->options->setPersistentId($persistentId);
        self::assertEquals($persistentId, $this->options->getPersistentId());
    }

    public function testSetPersistentIdImplicitlyEnablesPersistence(): void
    {
        self::assertFalse($this->options->isPersistent());
        $this->options->setPersistentId('foo');
        self::assertTrue($this->options->isPersistent());
    }

    public function testOptionsGetSetLibOptions(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);
        self::assertEquals(
            [RedisResource::OPT_SERIALIZER => RedisResource::SERIALIZER_PHP],
            $this->options->getLibOptions(),
        );
    }

    public function testGetSetServer(): void
    {
        $server = [
            'host'    => '127.0.0.1',
            'port'    => 6379,
            'timeout' => 0,
        ];
        $this->options->setServer($server);
        self::assertEquals($server, $this->options->getServer());
    }

    public function testOptionsGetSetDatabase(): void
    {
        $database = 1;
        $this->options->setDatabase($database);
        self::assertEquals($database, $this->options->getDatabase());
    }

    public function testOptionsGetSetPassword(): void
    {
        $password = 'my-secret';
        $this->options->setPassword($password);
        self::assertEquals(
            $password,
            $this->options->getPassword(),
        );
    }

    public function testOptionsGetSetUser(): void
    {
        $user = 'dummyuser';
        $this->options->setUser($user);
        self::assertEquals(
            $user,
            $this->options->getUser(),
        );
    }

    public function testParsesSocketFromServer(): void
    {
        $socket = '/tmp/redis.sock';
        $this->options->setServer($socket);
        self::assertEquals($socket, $this->options->getServer());
    }

    public function testParsesUriFromServer(): void
    {
        $this->options->setServer('redis://foo:bar@example.org:1234');
        self::assertEquals([
            'host'    => 'example.org',
            'port'    => 1234,
            'timeout' => 0,
        ], $this->options->getServer());
        self::assertEquals('foo', $this->options->getUser());
        self::assertEquals('bar', $this->options->getPassword());
    }

    /**
     * @group 6495
     */
    public function testPasswordFromOptionsOverridesPasswordFromUri(): void
    {
        $options = new RedisOptions([
            'server'   => 'redis://dummyuser:dummypass@testhost:1234',
            'password' => 'abcd1234',
        ]);

        $server = $options->getServer();
        self::assertIsArray($server);
        self::assertEquals('testhost', $server['host']);
        self::assertEquals(1234, $server['port'] ?? null);
        self::assertEquals('abcd1234', $options->getPassword());
        self::assertEquals('dummyuser', $options->getUser());
    }
}
