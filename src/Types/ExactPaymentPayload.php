<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Payment payload for exact scheme.
 */
class ExactPaymentPayload implements JsonSerializable
{
    /**
     * @param string $signature EIP-712 signature of the authorization
     * @param EIP3009Authorization $authorization EIP-3009 authorization details
     */
    public function __construct(
        public readonly string $signature,
        public readonly EIP3009Authorization $authorization
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
            signature: $data['signature'] ?? '',
            authorization: EIP3009Authorization::fromArray($data['authorization'] ?? [])
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
            'signature' => $this->signature,
            'authorization' => $this->authorization->toArray(),
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
