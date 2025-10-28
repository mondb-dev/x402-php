<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * EIP-3009 authorization structure for exact payment scheme.
 */
class EIP3009Authorization implements JsonSerializable
{
    /**
     * @param string $from Address initiating the transfer
     * @param string $to Address receiving the transfer
     * @param string $value Amount to transfer in atomic units
     * @param string $validAfter Unix timestamp after which the authorization is valid
     * @param string $validBefore Unix timestamp before which the authorization is valid
     * @param string $nonce Unique nonce for the authorization
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $value,
        public readonly string $validAfter,
        public readonly string $validBefore,
        public readonly string $nonce
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
            from: $data['from'] ?? '',
            to: $data['to'] ?? '',
            value: $data['value'] ?? '',
            validAfter: $data['validAfter'] ?? $data['valid_after'] ?? '',
            validBefore: $data['validBefore'] ?? $data['valid_before'] ?? '',
            nonce: $data['nonce'] ?? ''
        );
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'value' => $this->value,
            'validAfter' => $this->validAfter,
            'validBefore' => $this->validBefore,
            'nonce' => $this->nonce,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
