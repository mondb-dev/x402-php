<?php

declare(strict_types=1);

namespace X402\Nonce;

use X402\Exceptions\ValidationException;

/**
 * Interface for tracking used nonces to prevent replay attacks.
 */
interface NonceTrackerInterface
{
    /**
     * Check if a nonce has been used.
     *
     * @param string $nonce The nonce to check
     * @return bool True if nonce has been used, false otherwise
     */
    public function hasNonce(string $nonce): bool;

    /**
     * Check if a nonce has been used (alias for hasNonce).
     *
     * @param string $nonce The nonce to check
     * @return bool True if nonce has been used, false otherwise
     */
    public function isNonceUsed(string $nonce): bool;

    /**
     * Mark a nonce as used.
     * 
     * This method must be atomic - it should check and set in a single operation
     * to prevent race conditions.
     *
     * @param string $nonce The nonce to mark as used
     * @param int $ttlSeconds Time-to-live in seconds (after which nonce can be reused)
     * @return bool True if successfully marked, false if already used
     * @throws ValidationException If nonce format is invalid
     */
    public function markUsed(string $nonce, int $ttlSeconds): bool;

    /**
     * Mark a nonce as used (alias for markUsed).
     *
     * @param string $nonce The nonce to mark as used
     * @param int $ttlSeconds Time-to-live in seconds (after which nonce can be reused)
     * @return void
     * @throws ValidationException If nonce format is invalid or already used
     */
    public function markNonceUsed(string $nonce, int $ttlSeconds): void;

    /**
     * Remove a nonce from tracking (for testing/cleanup).
     *
     * @param string $nonce The nonce to remove
     * @return bool True if removed, false if didn't exist
     */
    public function remove(string $nonce): bool;
}
