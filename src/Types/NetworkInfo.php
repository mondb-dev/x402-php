<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Network information from facilitator.
 */
class NetworkInfo implements JsonSerializable
{
    /**
     * @param string $id Network identifier (e.g., 'base-mainnet')
     * @param string $name Human-readable network name
     * @param int $chainId Chain ID for EVM networks
     * @param string $type Network type ('evm' or 'svm')
     * @param string|null $rpcUrl RPC endpoint URL
     * @param string|null $explorerUrl Block explorer URL
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $chainId,
        public readonly string $type,
        public readonly ?string $rpcUrl = null,
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
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            chainId: (int)($data['chainId'] ?? $data['chain_id'] ?? 0),
            type: $data['type'] ?? 'evm',
            rpcUrl: $data['rpcUrl'] ?? $data['rpc_url'] ?? null,
            explorerUrl: $data['explorerUrl'] ?? $data['explorer_url'] ?? null
        );
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'id' => $this->id,
            'name' => $this->name,
            'chainId' => $this->chainId,
            'type' => $this->type,
        ];

        if ($this->rpcUrl !== null) {
            $array['rpcUrl'] = $this->rpcUrl;
        }

        if ($this->explorerUrl !== null) {
            $array['explorerUrl'] = $this->explorerUrl;
        }

        return $array;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
