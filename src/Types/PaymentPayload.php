<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Payment payload included in the X-PAYMENT header.
 */
class PaymentPayload implements JsonSerializable
{
    /**
     * @param int $x402Version Version of the x402 payment protocol
     * @param string $scheme Scheme of the payment (e.g., "exact")
     * @param string $network Network ID of the blockchain
     * @param mixed $payload Scheme-specific payload data
     */
    public function __construct(
        public readonly int $x402Version,
        public readonly string $scheme,
        public readonly string $network,
        public readonly mixed $payload
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
        $payload = $data['payload'] ?? [];
        
        // Parse scheme-specific payload
        $scheme = $data['scheme'] ?? '';
        $network = $data['network'] ?? '';
        
        if ($scheme === 'exact') {
            // Determine if EVM or SVM based on network
            if (self::isSvmNetwork($network) && is_array($payload)) {
                $payload = ExactSvmPayload::fromArray($payload);
            } elseif (is_array($payload)) {
                $payload = ExactPaymentPayload::fromArray($payload);
            }
        }

        return new self(
            x402Version: (int)($data['x402Version'] ?? $data['x402_version'] ?? 1),
            scheme: $scheme,
            network: $network,
            payload: $payload
        );
    }

    /**
     * Check if a network is SVM (Solana).
     *
     * @param string $network
     * @return bool
     */
    private static function isSvmNetwork(string $network): bool
    {
        return str_starts_with($network, 'solana-');
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = $this->payload;
        if ($payload instanceof JsonSerializable) {
            $payload = $payload->jsonSerialize();
        }

        return [
            'x402Version' => $this->x402Version,
            'scheme' => $this->scheme,
            'network' => $this->network,
            'payload' => $payload,
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
