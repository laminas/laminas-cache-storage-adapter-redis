<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception\RuntimeException;
use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Traversable;

final class RedisClusterOptions extends AdapterOptions
{
    /** @var string */
    protected $namespaceSeparator = ':';

    /** @var string */
    private $name = '';

    /** @var float */
    private $timeout = 1.0;

    /** @var float */
    private $readTimeout = 2.0;

    /** @var bool */
    private $persistent = false;

    /** @psalm-var list<non-empty-string> */
    private $seeds = [];

    /** @var string */
    private $version = '';

    /** @psalm-var array<int,mixed> */
    private $libOptions = [];

    /** @var RedisClusterResourceManagerInterface|null */
    private $resourceManager;

    /**
     * @param array|Traversable|null|AdapterOptions $options
     * @psalm-param array<string,mixed>|Traversable<string,mixed>|null|AdapterOptions $options
     */
    public function __construct($options = null)
    {
        if ($options instanceof AdapterOptions) {
            $options = $options->toArray();
        }

        /** @psalm-suppress InvalidArgument */
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
     * @psalm-param array<int,mixed> $options
     */
    public function setLibOptions(array $options): void
    {
        $this->libOptions = $options;
    }

    /**
     * @psalm-return array<int,mixed>
     */
    public function getLibOptions(): array
    {
        return $this->libOptions;
    }

    /**
     * @internal This method should only be used within this library to have better test coverage!
     */
    public function setResourceManager(RedisClusterResourceManagerInterface $resourceManager): void
    {
        $this->resourceManager = $resourceManager;
    }

    public function getResourceManager(): RedisClusterResourceManagerInterface
    {
        if ($this->resourceManager) {
            return $this->resourceManager;
        }

        return $this->resourceManager = new RedisClusterResourceManager($this);
    }
}
