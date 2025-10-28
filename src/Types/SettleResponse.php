<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Response from payment settlement.
 */
class SettleResponse implements JsonSerializable
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorReason = null,
        public readonly ?string $transaction = null,
        public readonly ?string $network = null,
        public readonly ?string $payer = null
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
            success: (bool)($data['success'] ?? false),
            errorReason: $data['errorReason'] ?? $data['error_reason'] ?? $data['error'] ?? null,
            transaction: $data['transaction'] ?? $data['txHash'] ?? $data['tx_hash'] ?? null,
            network: $data['network'] ?? $data['networkId'] ?? $data['network_id'] ?? null,
            payer: $data['payer'] ?? null
        );
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'success' => $this->success,
                'errorReason' => $this->errorReason,
                'transaction' => $this->transaction,
                'network' => $this->network,
                'payer' => $this->payer,
            ],
            static fn($value) => $value !== null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
