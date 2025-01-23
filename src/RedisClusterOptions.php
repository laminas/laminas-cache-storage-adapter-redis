<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception\RuntimeException;
use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Laminas\Stdlib\AbstractOptions;
use Traversable;

use function is_array;
use function iterator_to_array;

final class RedisClusterOptions extends AdapterOptions
{
    public const LIBRARY_OPTIONS = [
        self::OPT_SERIALIZER,
        self::OPT_PREFIX,
        self::OPT_READ_TIMEOUT,
        self::OPT_SCAN,
        self::OPT_SLAVE_FAILOVER,
        self::OPT_TCP_KEEPALIVE,
        self::OPT_COMPRESSION,
        self::OPT_REPLY_LITERAL,
        self::OPT_COMPRESSION_LEVEL,
        self::OPT_NULL_MULTIBULK_AS_NULL,
        self::OPT_MAX_RETRIES,
        self::OPT_BACKOFF_ALGORITHM,
        self::OPT_BACKOFF_BASE,
        self::OPT_BACKOFF_CAP,
    ];

    public const OPT_SERIALIZER             = 1;
    public const OPT_PREFIX                 = 2;
    public const OPT_READ_TIMEOUT           = 3;
    public const OPT_SCAN                   = 4;
    public const OPT_SLAVE_FAILOVER         = 5;
    public const OPT_TCP_KEEPALIVE          = 6;
    public const OPT_COMPRESSION            = 7;
    public const OPT_REPLY_LITERAL          = 8;
    public const OPT_COMPRESSION_LEVEL      = 9;
    public const OPT_NULL_MULTIBULK_AS_NULL = 10;
    public const OPT_MAX_RETRIES            = 11;
    public const OPT_BACKOFF_ALGORITHM      = 12;
    public const OPT_BACKOFF_BASE           = 13;
    public const OPT_BACKOFF_CAP            = 14;

    private string $namespaceSeparator = ':';

    private string $name = '';

    private float $timeout = 1.0;

    private float $readTimeout = 2.0;

    private bool $persistent = false;

    /** @psalm-var list<non-empty-string> */
    private array $seeds = [];

    private string $version = '';

    /** @psalm-var array<positive-int,mixed> */
    private array $libOptions = [];

    private string $user = '';

    private string $password = '';

    private ?SslContext $sslContext = null;

    /**
     * @param iterable<string,mixed>|null|AdapterOptions $options
     */
    public function __construct($options = null)
    {
        if ($options instanceof AdapterOptions) {
            $options = $options->toArray();
        }

        parent::__construct($options);
        $hasName  = $this->hasName();
        $hasSeeds = $this->getSeeds() !== [];

        if (! $hasName && ! $hasSeeds) {
            throw InvalidRedisClusterConfigurationException::fromMissingRequiredValues();
        }

        if ($hasName && $hasSeeds) {
            throw InvalidRedisClusterConfigurationException::fromNameAndSeedsProvidedViaConfiguration();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setFromArray($options): self
    {
        if ($options instanceof AbstractOptions) {
            $options = $options->toArray();
        } elseif ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        }

        $sslContext = $options['sslContext'] ?? $options['ssl_context'] ?? null;
        unset($options['sslContext'], $options['ssl_context']);
        if (is_array($sslContext)) {
            /** @psalm-suppress MixedArgumentTypeCoercion Trust upstream that they verify the array beforehand. */
            $sslContext = SslContext::fromSslContextArray($sslContext);
        }

        if ($sslContext instanceof SslContext) {
            $options['ssl_context'] = $sslContext;
        }

        parent::setFromArray($options);
        return $this;
    }

    public function setTimeout(float $timeout): void
    {
        $this->timeout = $timeout;
        $this->triggerOptionEvent('timeout', $timeout);
    }

    public function setReadTimeout(float $readTimeout): void
    {
        $this->readTimeout = $readTimeout;
        $this->triggerOptionEvent('read_timeout', $readTimeout);
    }

    public function setPersistent(bool $persistent): void
    {
        $this->persistent = $persistent;
    }

    public function getNamespaceSeparator(): string
    {
        return $this->namespaceSeparator;
    }

    public function setNamespaceSeparator(string $namespaceSeparator): void
    {
        if ($this->namespaceSeparator === $namespaceSeparator) {
            return;
        }

        $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
        $this->namespaceSeparator = $namespaceSeparator;
    }

    public function hasName(): bool
    {
        return $this->name !== '';
    }

    /**
     * @psalm-return non-empty-string
     * @throws RuntimeException If method is called but `name` was not provided via configuration.
     */
    public function getName(): string
    {
        $name = $this->name;
        if ($name === '') {
            throw new RuntimeException('`name` is not provided via configuration.');
        }

        return $name;
    }

    /**
     * @psalm-param non-empty-string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
        $this->triggerOptionEvent('name', $name);
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getReadTimeout(): float
    {
        return $this->readTimeout;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * @return array<int,string>
     * @psalm-return list<non-empty-string>
     */
    public function getSeeds(): array
    {
        return $this->seeds;
    }

    /**
     * @param array<int,string> $seeds
     * @psalm-param list<non-empty-string> $seeds
     */
    public function setSeeds(array $seeds): void
    {
        $this->seeds = $seeds;

        $this->triggerOptionEvent('seeds', $seeds);
    }

    /**
     * @param non-empty-string $version
     */
    public function setRedisVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getRedisVersion(): string
    {
        return $this->version;
    }

    /**
     * @psalm-param array<positive-int,mixed> $options
     */
    public function setLibOptions(array $options): void
    {
        $this->libOptions = $options;
    }

    /**
     * @psalm-return array<positive-int,mixed>
     */
    public function getLibOptions(): array
    {
        return $this->libOptions;
    }

    /**
     * @psalm-param RedisClusterOptions::OPT_* $option
     */
    public function getLibOption(int $option, mixed $default = null): mixed
    {
        return $this->libOptions[$option] ?? $default;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @psalm-param non-empty-string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @psalm-param non-empty-string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getSslContext(): ?SslContext
    {
        return $this->sslContext;
    }

    public function setSslContext(SslContext|null $sslContext): void
    {
        $this->sslContext = $sslContext;
    }
}
