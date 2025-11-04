<?php

declare(strict_types=1);

namespace X402\Nonce;

use X402\Exceptions\ValidationException;
use X402\Validation\Validator;

/**
 * Redis-based nonce tracker for preventing replay attacks.
 * 
 * Uses Redis SET NX (set if not exists) for atomic check-and-set operations.
 */
class RedisNonceTracker implements NonceTrackerInterface
{
    private const KEY_PREFIX = 'x402:nonce:';

    /**
     * @param \Redis $redis Redis client instance
     * @param string $namespace Namespace for nonce keys (e.g., app name)
     */
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $namespace = 'default'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function hasNonce(string $nonce): bool
    {
        if (!Validator::isValidNonce($nonce)) {
            throw new ValidationException("Invalid nonce format: $nonce");
        }

        $key = $this->getNonceKey($nonce);
        return $this->redis->exists($key) > 0;
    }

    /**
     * @inheritDoc
     * 
     * Uses Redis SET NX to atomically check and set the nonce.
     * This prevents race conditions where two requests with the same nonce
     * could both pass the hasNonce() check.
     */
    public function markUsed(string $nonce, int $ttlSeconds): bool
    {
        if (!Validator::isValidNonce($nonce)) {
            throw new ValidationException("Invalid nonce format: $nonce");
        }

        if ($ttlSeconds <= 0) {
            throw new ValidationException("TTL must be positive");
        }

        $key = $this->getNonceKey($nonce);
        
        // Use SET NX (set if not exists) with expiry - atomic operation
        // Returns false if key already exists (nonce was already used)
        $result = $this->redis->set(
            $key,
            (string)time(),
            ['NX', 'EX' => $ttlSeconds]
        );
        
        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function isNonceUsed(string $nonce): bool
    {
        return $this->hasNonce($nonce);
    }

    /**
     * @inheritDoc
     */
    public function markNonceUsed(string $nonce, int $ttlSeconds): void
    {
        $result = $this->markUsed($nonce, $ttlSeconds);
        if (!$result) {
            throw new ValidationException("Nonce has already been used: $nonce");
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(string $nonce): bool
    {
        if (!Validator::isValidNonce($nonce)) {
            throw new ValidationException("Invalid nonce format: $nonce");
        }

        $key = $this->getNonceKey($nonce);
        return $this->redis->del($key) > 0;
    }

    /**
     * Get the Redis key for a nonce.
     *
     * @param string $nonce
     * @return string
     */
    private function getNonceKey(string $nonce): string
    {
        return self::KEY_PREFIX . $this->namespace . ':' . $nonce;
    }
}
