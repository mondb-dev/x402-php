<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Response from payment verification.
 */
class VerifyResponse implements JsonSerializable
{
    /**
     * @param bool $isValid Whether the payment is valid
     * @param string|null $invalidReason Reason if payment is invalid
     * @param string|null $payer Address of the payer
     * @param array<string, mixed>|null $details Additional details about validation failure (Coinbase Facilitator)
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $invalidReason = null,
        public readonly ?string $payer = null,
        public readonly ?array $details = null
    ) {
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isValid: (bool)($data['isValid'] ?? $data['is_valid'] ?? false),
            invalidReason: $data['invalidReason'] ?? $data['invalid_reason'] ?? null,
            payer: $data['payer'] ?? null,
            details: $data['details'] ?? null
        );
    }

    /**
     * Get detailed error information.
     *
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * Get a specific detail field.
     *
     * @param string $key
     * @return mixed
     */
    public function getDetail(string $key): mixed
    {
        return $this->details[$key] ?? null;
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'isValid' => $this->isValid,
        ];

        if ($this->invalidReason !== null) {
            $result['invalidReason'] = $this->invalidReason;
        }

        if ($this->payer !== null) {
            $result['payer'] = $this->payer;
        }

        if ($this->details !== null) {
            $result['details'] = $this->details;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
