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
     * @param bool $success Whether the payment was successful
     * @param string|null $error Error message from the facilitator server
     * @param string|null $txHash Transaction hash of the settled payment
     * @param string|null $networkId Network id of the blockchain the payment was settled on
     * @param string|null $payer Address of the payer
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $error = null,
        public readonly ?string $txHash = null,
        public readonly ?string $networkId = null,
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
            error: $data['error'] ?? $data['errorReason'] ?? $data['error_reason'] ?? null,
            txHash: $data['txHash'] ?? $data['transaction'] ?? $data['tx_hash'] ?? null,
            networkId: $data['networkId'] ?? $data['network'] ?? $data['network_id'] ?? null,
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
        $result = [
            'success' => $this->success,
        ];

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        if ($this->txHash !== null) {
            $result['txHash'] = $this->txHash;
        }

        if ($this->networkId !== null) {
            $result['networkId'] = $this->networkId;
        }

        if ($this->payer !== null) {
            $result['payer'] = $this->payer;
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
