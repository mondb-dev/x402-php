<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Payment payload for exact scheme on Solana (SVM - Solana Virtual Machine).
 * 
 * For Solana, the payload contains a base64-encoded transaction that includes
 * the SPL token transfer instruction. The transaction is partially signed by
 * the client and will be completed by the facilitator.
 */
class ExactSvmPayload implements JsonSerializable
{
    /**
     * @param string $transaction Base64-encoded Solana transaction
     */
    public function __construct(
        public readonly string $transaction
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
            transaction: $data['transaction'] ?? ''
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
            'transaction' => $this->transaction,
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
