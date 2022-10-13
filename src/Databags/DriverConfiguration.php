<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Databags;

use function call_user_func;
use Composer\InstalledVersions;
use function function_exists;
use function is_callable;
use Laudis\Neo4j\Common\Cache;
use Laudis\Neo4j\Common\SemaphoreFactory;
use Laudis\Neo4j\Contracts\SemaphoreFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use function sprintf;

/**
 * Configuration object for the driver.
 */
final class DriverConfiguration
{
    public const DEFAULT_USER_AGENT = 'neo4j-php-client/%s';
    public const DEFAULT_POOL_SIZE = 0x2F;
    public const DEFAULT_CACHE_IMPLEMENTATION = Cache::class;
    public const DEFAULT_ACQUIRE_CONNECTION_TIMEOUT = 2.0;

    private ?string $userAgent;
    /** @var callable():(HttpPsrBindings|null)|HttpPsrBindings|null */
    private $httpPsrBindings;
    private SslConfiguration $sslConfig;
    private ?int $maxPoolSize;
    /** @var callable():(CacheInterface|null)|CacheInterface|null */
    private $cache;
    /** @var callable():(SemaphoreFactoryInterface|null)|SemaphoreFactoryInterface|null */
    private $semaphoreFactory;
    /** @var ?float */
    private ?float $acquireConnectionTimeout;

    /**
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     * @param callable():(CacheInterface|null)|CacheInterface|null $cache
     * @param callable():(SemaphoreFactoryInterface|null)|SemaphoreFactoryInterface|null $semaphore
     *
     * @psalm-immutable
     */
    public function __construct(?string $userAgent, $httpPsrBindings, SslConfiguration $sslConfig, ?int $maxPoolSize, $cache, ?float $acquireConnectionTimeout, $semaphore)
    {
        $this->userAgent = $userAgent;
        $this->httpPsrBindings = $httpPsrBindings;
        $this->sslConfig = $sslConfig;
        $this->maxPoolSize = $maxPoolSize;
        $this->cache = $cache;
        $this->acquireConnectionTimeout = $acquireConnectionTimeout;
        $this->semaphoreFactory = $semaphore;
    }

    /**
     * @param pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     *
     * @psalm-immutable
     */
    public static function create(?string $userAgent, $httpPsrBindings, SslConfiguration $sslConfig, int $maxPoolSize, CacheInterface $cache, float $acquireConnectionTimeout, SemaphoreFactoryInterface $semaphore): self
    {
        return new self($userAgent, $httpPsrBindings, $sslConfig, $maxPoolSize, $cache, $acquireConnectionTimeout, $semaphore);
    }

    /**
     * Creates a default configuration with a user agent based on the driver version
     * and HTTP PSR implementation auto detected from the environment.
     *
     * @psalm-immutable
     */
    public static function default(): self
    {
        return new self(null, HttpPsrBindings::default(), SslConfiguration::default(), null, null, null, null);
    }

    public function getUserAgent(): string
    {
        if ($this->userAgent === null) {
            if (function_exists('InstalledVersions::getPrettyVersion')) {
                /** @psalm-suppress ImpureMethodCall */
                $version = InstalledVersions::getPrettyVersion('laudis/neo4j-php-client') ?? 'provided/replaced';
            } else {
                $version = '2';
            }

            $this->userAgent = sprintf(self::DEFAULT_USER_AGENT, $version);
        }

        return $this->userAgent;
    }

    /**
     * Creates a new configuration with the provided user agent.
     *
     * @param string|null $userAgent
     *
     * @psalm-immutable
     */
    public function withUserAgent($userAgent): self
    {
        $tbr = clone $this;
        $tbr->userAgent = $userAgent;

        return $tbr;
    }

    /**
     * Creates a new configuration with the provided bindings.
     *
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $bindings
     *
     * @psalm-immutable
     */
    public function withHttpPsrBindings($bindings): self
    {
        $tbr = clone $this;
        $tbr->httpPsrBindings = $bindings;

        return $tbr;
    }

    /**
     * @psalm-immutable
     */
    public function withSslConfiguration(SslConfiguration $config): self
    {
        $tbr = clone $this;
        $tbr->sslConfig = $config;

        return $tbr;
    }

    /**
     * @psalm-immutable
     */
    public function getSslConfiguration(): SslConfiguration
    {
        return $this->sslConfig;
    }

    public function getHttpPsrBindings(): HttpPsrBindings
    {
        $this->httpPsrBindings = (is_callable($this->httpPsrBindings)) ? call_user_func($this->httpPsrBindings) : $this->httpPsrBindings;

        return $this->httpPsrBindings ??= HttpPsrBindings::default();
    }

    public function getMaxPoolSize(): int
    {
        return $this->maxPoolSize ?? self::DEFAULT_POOL_SIZE;
    }

    /**
     * @psalm-immutable
     */
    public function withMaxPoolSize(?int $maxPoolSize): self
    {
        $tbr = clone $this;
        $tbr->maxPoolSize = $maxPoolSize;

        return $tbr;
    }

    /**
     * @param callable():(CacheInterface|null)|CacheInterface|null $cache
     *
     * @psalm-immutable
     */
    public function withCache($cache): self
    {
        $tbr = clone $this;
        $tbr->cache = $cache;

        return $tbr;
    }

    public function getCache(): CacheInterface
    {
        $this->cache = (is_callable($this->cache)) ? call_user_func($this->cache) : $this->cache;

        return $this->cache ??= Cache::getInstance();
    }

    public function getSemaphoreInterface(): SemaphoreFactoryInterface
    {
        $this->semaphoreFactory = (is_callable($this->semaphoreFactory)) ? call_user_func($this->semaphoreFactory) : $this->semaphoreFactory;

        return $this->semaphoreFactory ??= SemaphoreFactory::getInstance();
    }

    /**
     * @psalm-immutable
     */
    public function getAcquireConnectionTimeout(): float
    {
        return $this->acquireConnectionTimeout ??= self::DEFAULT_ACQUIRE_CONNECTION_TIMEOUT;
    }

    /**
     * @psalm-immutable
     */
    public function withAcquireConnectionTimeout(?float $acquireConnectionTimeout): self
    {
        $tbr = clone $this;
        $tbr->acquireConnectionTimeout = $acquireConnectionTimeout;

        return $tbr;
    }
}
