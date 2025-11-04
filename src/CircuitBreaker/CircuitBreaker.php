<?php

declare(strict_types=1);

namespace X402\CircuitBreaker;

/**
 * Circuit breaker pattern implementation.
 * 
 * Prevents cascading failures by stopping calls to a failing service
 * and allowing it time to recover.
 */
class CircuitBreaker
{
    private int $failureCount = 0;
    private ?int $openUntil = null;
    private int $successCount = 0;

    /**
     * @param int $failureThreshold Number of failures before opening circuit
     * @param int $recoveryTimeout Seconds to wait before trying again (half-open state)
     * @param int $successThreshold Number of successes needed to close circuit from half-open
     */
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 60,
        private readonly int $successThreshold = 2
    ) {
        if ($failureThreshold <= 0) {
            throw new \InvalidArgumentException('Failure threshold must be positive');
        }
        if ($recoveryTimeout <= 0) {
            throw new \InvalidArgumentException('Recovery timeout must be positive');
        }
        if ($successThreshold <= 0) {
            throw new \InvalidArgumentException('Success threshold must be positive');
        }
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @template T
     * @param callable(): T $fn Function to execute
     * @return T Result of function call
     * @throws CircuitOpenException If circuit is open
     */
    public function call(callable $fn): mixed
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException(
                sprintf('Circuit breaker is open until %s', date('Y-m-d H:i:s', $this->openUntil))
            );
        }

        try {
            $result = $fn();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Check if circuit is open.
     *
     * @return bool True if circuit is open (calls should be blocked)
     */
    public function isOpen(): bool
    {
        // Circuit is open if we're before the recovery time
        if ($this->openUntil !== null && time() < $this->openUntil) {
            return true;
        }

        // Recovery period passed, enter half-open state
        if ($this->openUntil !== null && time() >= $this->openUntil) {
            $this->openUntil = null;
            $this->successCount = 0;
            // Return false to allow testing the service
        }

        return false;
    }

    /**
     * Check if circuit is closed (normal operation).
     *
     * @return bool True if circuit is closed
     */
    public function isClosed(): bool
    {
        return $this->openUntil === null && $this->failureCount < $this->failureThreshold;
    }

    /**
     * Check if circuit is in half-open state (testing recovery).
     *
     * @return bool True if half-open
     */
    public function isHalfOpen(): bool
    {
        return !$this->isOpen() && !$this->isClosed();
    }

    /**
     * Record a successful call.
     *
     * @return void
     */
    public function recordSuccess(): void
    {
        if ($this->isHalfOpen()) {
            $this->successCount++;
            
            // Close circuit if enough successes
            if ($this->successCount >= $this->successThreshold) {
                $this->reset();
            }
        } else {
            // Reset failure count on success in closed state
            $this->failureCount = 0;
        }
    }

    /**
     * Record a failed call.
     *
     * @return void
     */
    public function recordFailure(): void
    {
        $this->failureCount++;
        $this->successCount = 0;

        // Open circuit if threshold reached
        if ($this->failureCount >= $this->failureThreshold) {
            $this->openUntil = time() + $this->recoveryTimeout;
        }
    }

    /**
     * Reset circuit breaker to closed state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->failureCount = 0;
        $this->openUntil = null;
        $this->successCount = 0;
    }

    /**
     * Get circuit state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return [
            'open' => $this->isOpen(),
            'closed' => $this->isClosed(),
            'half_open' => $this->isHalfOpen(),
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'open_until' => $this->openUntil ? date('Y-m-d H:i:s', $this->openUntil) : null,
        ];
    }
}
