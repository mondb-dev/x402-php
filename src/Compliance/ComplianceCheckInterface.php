<?php

declare(strict_types=1);

namespace X402\Compliance;

use X402\Exceptions\ComplianceException;

/**
 * Result of a compliance check.
 */
class ComplianceResult
{
    public function __construct(
        private readonly bool $blocked,
        private readonly ?string $reason = null,
        private readonly array $metadata = []
    ) {}

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}

/**
 * Interface for AML/KYC compliance checks.
 */
interface ComplianceCheckInterface
{
    /**
     * Check if an address is compliant (not sanctioned).
     *
     * @param string $address Address to check
     * @param string $network Network ID
     * @return bool True if compliant, false if sanctioned
     * @throws ComplianceException If check fails or address is sanctioned
     */
    public function isCompliant(string $address, string $network): bool;

    /**
     * Check an address and return detailed result.
     *
     * @param string $address Address to check
     * @param string $network Network ID
     * @return ComplianceResult Compliance check result
     */
    public function checkAddress(string $address, string $network): ComplianceResult;

    /**
     * Get compliance status with detailed information.
     *
     * @param string $address Address to check
     * @param string $network Network ID
     * @return array<string, mixed> Compliance status details
     */
    public function getComplianceStatus(string $address, string $network): array;
}
