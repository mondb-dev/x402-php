<?php

declare(strict_types=1);

namespace X402\RateLimit;

/**
 * Redis-based sliding window rate limiter.
 * 
 * Uses Redis sorted sets to implement a sliding window rate limiter
 * that prevents DoS attacks and abuse.
 */
class RedisRateLimiter implements RateLimiterInterface
{
    private const KEY_PREFIX = 'x402:ratelimit:';

    /**
     * @param \Redis $redis Redis client instance
     * @param int $maxAttempts Maximum attempts allowed in time window
     * @param int $decaySeconds Time window in seconds
     * @param string $namespace Namespace for rate limit keys (e.g., app name)
     */
    public function __construct(
        private readonly \Redis $redis,
        private readonly int $maxAttempts = 10,
        private readonly int $decaySeconds = 60,
        private readonly string $namespace = 'default'
    ) {
        if ($maxAttempts <= 0) {
            throw new \InvalidArgumentException('maxAttempts must be positive');
        }

        if ($decaySeconds <= 0) {
            throw new \InvalidArgumentException('decaySeconds must be positive');
        }
    }

    /**
     * @inheritDoc
     */
    public function isAllowed(string $identifier): bool
    {
        $key = $this->getRateLimitKey($identifier);
        $now = microtime(true);
        $windowStart = $now - $this->decaySeconds;

        // Remove old entries outside the window
        $this->redis->zRemRangeByScore($key, '-inf', (string)$windowStart);

        // Count entries in current window
        $count = $this->redis->zCard($key);

        return $count < $this->maxAttempts;
    }

    /**
     * @inheritDoc
     */
    public function recordAttempt(string $identifier): int
    {
        $key = $this->getRateLimitKey($identifier);
        $now = microtime(true);
        $windowStart = $now - $this->decaySeconds;

        // Remove old entries outside the window
        $this->redis->zRemRangeByScore($key, '-inf', (string)$windowStart);

        // Add current attempt
        $this->redis->zAdd($key, $now, (string)$now);

        // Set expiry on key (decay + buffer)
        $this->redis->expire($key, $this->decaySeconds + 10);

        // Return current count
        return (int)$this->redis->zCard($key);
    }

    /**
     * @inheritDoc
     */
    public function recordSuccess(string $identifier): void
    {
        $key = $this->getRateLimitKey($identifier);
        
        // Remove one attempt from the window (reduce penalty)
        $members = $this->redis->zRange($key, 0, 0);
        if (!empty($members)) {
            $this->redis->zRem($key, $members[0]);
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(string $identifier): bool
    {
        $key = $this->getRateLimitKey($identifier);
        return $this->redis->del($key) > 0;
    }

    /**
     * @inheritDoc
     */
    public function tooManyAttempts(string $identifier): bool
    {
        return !$this->isAllowed($identifier);
    }

    /**
     * @inheritDoc
     */
    public function attempt(string $identifier): void
    {
        $this->recordAttempt($identifier);
    }

    /**
     * @inheritDoc
     */
    public function availableIn(string $identifier): int
    {
        // Return decay seconds as a simple approximation
        return $this->decaySeconds;
    }

    /**
     * @inheritDoc
     */
    public function getRemainingAttempts(string $identifier): int
    {
        $key = $this->getRateLimitKey($identifier);
        $now = microtime(true);
        $windowStart = $now - $this->decaySeconds;

        // Remove old entries
        $this->redis->zRemRangeByScore($key, '-inf', (string)$windowStart);

        // Count current attempts
        $count = (int)$this->redis->zCard($key);

        return max(0, $this->maxAttempts - $count);
    }

    /**
     * Get the Redis key for a rate limit identifier.
     *
     * @param string $identifier
     * @return string
     */
    private function getRateLimitKey(string $identifier): string
    {
        // Hash identifier to prevent key injection
        $hash = hash('sha256', $identifier);
        return self::KEY_PREFIX . $this->namespace . ':' . $hash;
    }
}
