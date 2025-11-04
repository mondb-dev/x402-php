<?php

declare(strict_types=1);

namespace X402\RateLimit;

/**
 * Interface for rate limiting to prevent DoS attacks.
 */
interface RateLimiterInterface
{
    /**
     * Check if an identifier is allowed to proceed.
     *
     * @param string $identifier Identifier to rate limit (e.g., IP address, user ID)
     * @return bool True if allowed, false if rate limit exceeded
     */
    public function isAllowed(string $identifier): bool;

    /**
     * Check if identifier has too many attempts.
     *
     * @param string $identifier Identifier to check
     * @return bool True if rate limit exceeded, false otherwise
     */
    public function tooManyAttempts(string $identifier): bool;

    /**
     * Record an attempt for an identifier.
     *
     * @param string $identifier Identifier to record attempt for
     * @return int Number of attempts in current window
     */
    public function recordAttempt(string $identifier): int;

    /**
     * Record an attempt (alias for recordAttempt).
     *
     * @param string $identifier Identifier to record attempt for
     * @return void
     */
    public function attempt(string $identifier): void;

    /**
     * Get seconds until rate limit resets for identifier.
     *
     * @param string $identifier Identifier to check
     * @return int Seconds until reset
     */
    public function availableIn(string $identifier): int;

    /**
     * Record a successful operation (optionally reduce penalty).
     *
     * @param string $identifier Identifier to record success for
     * @return void
     */
    public function recordSuccess(string $identifier): void;

    /**
     * Reset rate limit for an identifier.
     *
     * @param string $identifier Identifier to reset
     * @return bool True if reset, false if didn't exist
     */
    public function reset(string $identifier): bool;

    /**
     * Get remaining attempts for an identifier.
     *
     * @param string $identifier Identifier to check
     * @return int Number of remaining attempts
     */
    public function getRemainingAttempts(string $identifier): int;
}
