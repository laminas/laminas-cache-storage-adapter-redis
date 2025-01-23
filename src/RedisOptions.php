<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception;
use Redis;

use function array_is_list;
use function array_key_exists;
use function constant;
use function defined;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function parse_url;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function trim;

/**
 * @psalm-type ServerArrayShape = array{
 *  host: non-empty-string,
 *  port: int<1,65535>,
 *  timeout: non-negative-int|null
 * }
 */
final class RedisOptions extends AdapterOptions
{
    public const LIBRARY_OPTIONS = [
        self::OPT_SERIALIZER,
        self::OPT_PREFIX,
        self::OPT_READ_TIMEOUT,
        self::OPT_SCAN,
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
    public const OPT_TCP_KEEPALIVE          = 6;
    public const OPT_COMPRESSION            = 7;
    public const OPT_REPLY_LITERAL          = 8;
    public const OPT_COMPRESSION_LEVEL      = 9;
    public const OPT_NULL_MULTIBULK_AS_NULL = 10;
    public const OPT_MAX_RETRIES            = 11;
    public const OPT_BACKOFF_ALGORITHM      = 12;
    public const OPT_BACKOFF_BASE           = 13;
    public const OPT_BACKOFF_CAP            = 14;

    /**
     * Prioritized properties ordered by prio to be set first
     * in case a bulk of options sets set at once
     *
     * @var string[]
     */
    // @codingStandardsIgnoreStart
    protected array $__prioritizedProperties__ = ['server', 'persistentId'];
    // @codingStandardsIgnoreEnd
    /**
     * The namespace separator
     */
    protected string $namespaceSeparator = ':';
    /** @var non-empty-string|null */
    protected string|null $persistentId;

    protected string $redisVersion = '';
    /** @var non-empty-string|null */
    protected string|null $user;
    /** @var array<positive-int,mixed> */
    protected array $libOptions = [];
    /** @var ServerArrayShape|non-empty-string|null */
    protected array|null|string $server;
    protected int $database = 0;
    /** @var non-empty-string|null */
    protected string|null $password;
    protected bool $persistent = false;

    /**
     * @param iterable<string,mixed>|null|AdapterOptions $options
     */
    public function __construct(iterable|null|AdapterOptions $options = null)
    {
        $this->user         = null;
        $this->password     = null;
        $this->server       = null;
        $this->persistentId = null;
        parent::__construct($options);
    }

    /**
     * {@inheritDoc}
     */
    public function setNamespace(string $namespace): self
    {
        if (128 < strlen($namespace)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a prefix key of no longer than 128 characters',
                __METHOD__
            ));
        }

        parent::setNamespace($namespace);
        return $this;
    }

    public function setNamespaceSeparator(string $namespaceSeparator): self
    {
        $this->namespaceSeparator = $namespaceSeparator;
        $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
        return $this;
    }

    public function getNamespaceSeparator(): string
    {
        return $this->namespaceSeparator;
    }

    /**
     * @return non-empty-string|null
     */
    public function getPersistentId(): string|null
    {
        return $this->persistentId;
    }

    public function setPersistentId(string $persistentId): void
    {
        if ($persistentId === '') {
            return;
        }

        $this->persistentId = $persistentId;
        $this->persistent   = true;
        $this->triggerOptionEvent('persistent_id', $persistentId);
    }

    /**
     * @param array<positive-int|non-empty-string,mixed> $options
     */
    public function setLibOptions(array $options): void
    {
        $this->libOptions = $this->normalizeLibOptions($options);
        $this->triggerOptionEvent('lib_option', $options);
    }

    /**
     * @return array<positive-int,mixed> $options
     */
    public function getLibOptions(): array
    {
        return $this->libOptions;
    }

    /**
     * @param positive-int $option
     */
    public function getLibOption(int $option, mixed $default = null): mixed
    {
        return $this->libOptions[$option] ?? $default;
    }

    /**
     * Server can be described as follows:
     * - URI:   /path/to/sock.sock or redis://user:pass@host:port
     * - Assoc: array('host' => <host>[, 'port' => <port>[, 'timeout' => <timeout>]])
     * - List:  array(<host>[, <port>, [, <timeout>]])
     *
     * @param ServerArrayShape|array{0:non-empty-string,1?:int<1,65535>,2?:non-negative-int}|non-empty-string $server
     */
    public function setServer(string|array $server): self
    {
        [$server, $username, $password] = $this->normalizeServer($server);
        if ($username !== null) {
            $this->setUser($username);
        }

        if ($password !== null) {
            $this->setPassword($password);
        }

        $this->server = $server;
        return $this;
    }

    /**
     * @return ServerArrayShape|non-empty-string
     * @throws Exception\RuntimeException In case there is no server provided by configuration.
     */
    public function getServer(): array|string
    {
        if ($this->server === null) {
            throw new Exception\RuntimeException('Missing `server` option.');
        }

        return $this->server;
    }

    public function setDatabase(int $database): void
    {
        $this->database = $database;
    }

    public function getDatabase(): int
    {
        return $this->database;
    }

    public function setPassword(string $password): void
    {
        if ($password === '') {
            return;
        }

        $this->password = $password;
    }

    /**
     * @return non-empty-string|null
     */
    public function getPassword(): string|null
    {
        return $this->password;
    }

    public function setUser(string $user): void
    {
        if ($user === '') {
            return;
        }

        $this->user = $user;
    }

    /**
     * @return non-empty-string|null
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getRedisVersion(): string
    {
        return $this->redisVersion;
    }

    /**
     * @param non-empty-string $version
     */
    public function setRedisVersion(string $version): void
    {
        $this->redisVersion = trim($version);
    }

    /**
     * @param ServerArrayShape|array{0:non-empty-string,1?:int<1,65535>,2?:non-negative-int}|non-empty-string $server
     * @return array{
     *     0: ServerArrayShape|non-empty-string,
     *     1: non-empty-string|null,
     *     2: non-empty-string|null
     * }
     */
    private function normalizeServer(array|string $server): array
    {
        /**
         * @psalm-suppress TypeDoesNotContainType Psalm types do not prevent users from injecting empty strings.
         */
        if ($server === [] || $server === '') {
            throw new Exception\InvalidArgumentException('Provided `server` configuration must hold any information.');
        }

        if (is_string($server)) {
            if (str_starts_with($server, '/')) {
                return [$server, null, null];
            }

            $parsedUrl = parse_url($server);
            if (! is_array($parsedUrl)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Provided `server` is not a valid URI; "%s" given.',
                    $server,
                ));
            }

            $server = $parsedUrl;
            unset($parsedUrl);
        }

        if (array_is_list($server)) {
            return [$this->createServerArrayShape($server[0], $server[1] ?? 6379, $server[2] ?? 0), null, null];
        }

        if (! array_key_exists('host', $server)) {
            throw new Exception\InvalidArgumentException('Missing required `host` option in server configuration.');
        }

        $host     = $server['host'];
        $port     = $server['port'] ?? 6379;
        $timeout  = $server['timeout'] ?? 0;
        $user     = $server['user'] ?? null;
        $password = $server['pass'] ?? null;

        /**
         * @psalm-suppress TypeDoesNotContainType Psalm types do not provide that kind of type-safety here and
         *                                        thus we should double-check here.
         */
        if ($user !== null && (! is_string($user) || $user === '')) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Provided `user` option in server configuration must be a non-empty-string; "%s" given.',
                get_debug_type($user),
            ));
        }

        /**
         * @psalm-suppress TypeDoesNotContainType Psalm types do not provide that kind of type-safety here and
         *                                        thus we should double-check here.
         */
        if ($password !== null && (! is_string($password) || $password === '')) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Provided `password` option in server configuration must be a non-empty-string; "%s" given.',
                get_debug_type($password),
            ));
        }

        return [
            $this->createServerArrayShape($host, $port, $timeout),
            $user,
            $password,
        ];
    }

    /**
     * @return ServerArrayShape
     */
    private function createServerArrayShape(mixed $host, mixed $port, mixed $timeout): array
    {
        if (! is_string($host) || $host === '') {
            throw new Exception\InvalidArgumentException(sprintf(
                'Provided `host` option in server configuration must be a non-empty-string; "%s" given.',
                get_debug_type($host),
            ));
        }

        if (! is_int($port) || $port < 1 || $port > 65535) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Provided `port` option in server configuration must be a positive integer between 1 and 65535;'
                . ' "%s" given.',
                get_debug_type($port),
            ));
        }

        if (! is_int($timeout) || $timeout < 0) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Provided `timeout` option in server configuration must be a non-negative-int; "%s" given.',
                get_debug_type($port),
            ));
        }

        return ['host' => $host, 'port' => $port, 'timeout' => $timeout];
    }

    public function setPersistent(bool $persistent): void
    {
        $this->persistent = $persistent;
        if ($persistent === false) {
            $this->persistentId = null;
        }
    }

    /**
     * @internal Only providing this method for having tests passed. Please use {@see RedisOptions::isPersistent()}.
     *
     * @psalm-suppress PossiblyUnusedMethod Method just exists to have tests passing.
     */
    public function getPersistent(): bool
    {
        return $this->isPersistent();
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * @param array<positive-int|non-empty-string,mixed> $options
     * @return array<positive-int,mixed>
     */
    private function normalizeLibOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $option => $value) {
            if (is_string($option)) {
                $constant = sprintf('\Redis::OPT_%s', strtoupper($option));
                if (! defined($constant)) {
                    throw new Exception\InvalidArgumentException(sprintf(
                        'Provided redis option `%s` does not exist (anymore).',
                        $option,
                    ));
                }

                /** @var value-of<Redis::OPT_*> $option */
                $option = constant($constant);
            }

            $normalized[$option] = $value;
        }

        return $normalized;
    }
}
