<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisConfigurationException;
use Traversable;

final class RedisClusterOptions extends AdapterOptions
{
    /** @var string */
    protected $namespaceSeparator = ':';

    /** @var string */
    private $nodename = '';

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
        $hasNodename = $this->hasNodename();
        $hasSeeds    = $this->seeds() !== [];

        if (! $hasNodename && ! $hasSeeds) {
            throw InvalidRedisConfigurationException::fromMissingRequiredValues();
        }

        if ($hasNodename && $hasSeeds) {
            throw InvalidRedisConfigurationException::nodenameAndSeedsProvided();
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

    public function hasNodename(): bool
    {
        return $this->nodename !== '';
    }

    public function nodename(): string
    {
        return $this->nodename;
    }

    public function setNodename(string $nodename): void
    {
        $this->nodename = $nodename;
        $this->triggerOptionEvent('nodename', $nodename);
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function readTimeout(): float
    {
        return $this->readTimeout;
    }

    public function persistent(): bool
    {
        return $this->persistent;
    }

    /**
     * @return array<int,string>
     * @psalm-return list<non-empty-string>
     */
    public function seeds(): array
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

    public function redisVersion(): string
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
    public function libOptions(): array
    {
        return $this->libOptions;
    }

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
