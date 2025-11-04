<?php

declare(strict_types=1);

namespace X402\Health;

/**
 * Health check status result.
 */
class HealthStatus
{
    public function __construct(
        public readonly bool $healthy,
        public readonly array $checks,
        public readonly ?\DateTimeImmutable $timestamp = null
    ) {}

    /**
     * Check if all components are healthy.
     *
     * @return bool True if all healthy
     */
    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    /**
     * Get failed checks.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFailedChecks(): array
    {
        return array_filter($this->checks, fn($check) => !($check['healthy'] ?? false));
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'healthy' => $this->healthy,
            'checks' => $this->checks,
            'timestamp' => ($this->timestamp ?? new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
        ];
    }
}
