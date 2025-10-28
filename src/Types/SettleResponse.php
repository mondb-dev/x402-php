<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Response from payment settlement.
 */
class SettleResponse implements JsonSerializable
{
    /**
     * @param bool $success Whether settlement was successful
     * @param string|null $errorReason Error reason if settlement failed
     * @param string|null $transaction On-chain transaction hash
     * @param string|null $network Network where transaction was submitted
     * @param string|null $payer Address of the payer
     * @param string|null $status Transaction status (pending, confirmed, failed)
     * @param string|null $submittedAt ISO 8601 timestamp when transaction was submitted
     * @param string|null $explorerUrl Block explorer URL for the transaction
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorReason = null,
        public readonly ?string $transaction = null,
        public readonly ?string $network = null,
        public readonly ?string $payer = null,
        public readonly ?string $status = null,
        public readonly ?string $submittedAt = null,
        public readonly ?string $explorerUrl = null
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
            transaction: $data['transaction'] ?? $data['transactionHash'] ?? $data['transaction_hash'] ?? $data['txHash'] ?? $data['tx_hash'] ?? null,
            network: $data['network'] ?? $data['networkId'] ?? $data['network_id'] ?? null,
            payer: $data['payer'] ?? null,
            status: $data['status'] ?? null,
            submittedAt: $data['submittedAt'] ?? $data['submitted_at'] ?? null,
            explorerUrl: $data['explorerUrl'] ?? $data['explorer_url'] ?? null
        );
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed' || !$this->success;
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
                'status' => $this->status,
                'submittedAt' => $this->submittedAt,
                'explorerUrl' => $this->explorerUrl,
            ],
            static fn($value) => $value !== null || is_bool($value)
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
