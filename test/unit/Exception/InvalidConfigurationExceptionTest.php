<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Exception;

use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use PHPUnit\Framework\TestCase;

final class InvalidConfigurationExceptionTest extends TestCase
{
    public function testInstanceOfLaminasCacheException(): void
    {
        $exception = new InvalidRedisClusterConfigurationException();
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }
}
