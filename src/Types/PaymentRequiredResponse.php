<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Response returned by a server alongside a 402 Payment Required status code.
 */
class PaymentRequiredResponse implements JsonSerializable
{
    /**
     * @param int $x402Version Version of the x402 payment protocol
     * @param array<PaymentRequirements> $accepts List of payment requirements that the resource server accepts
     * @param string $error Message from the resource server to communicate errors
     */
    public function __construct(
        public readonly int $x402Version,
        public readonly array $accepts,
        public readonly string $error = ''
    ) {
    }

    /**
     * Get the HTTP headers required for a 402 Payment Required response.
     * 
     * Per x402 protocol specification, 402 responses must include:
     * - WWW-Authenticate: X-Payment
     * - Content-Type: application/json
     * - X-Payment-Accept: (comma-separated list of accepted schemes)
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $schemes = [];
        foreach ($this->accepts as $requirement) {
            if (!in_array($requirement->scheme, $schemes, true)) {
                $schemes[] = $requirement->scheme;
            }
        }

        return [
            'WWW-Authenticate' => 'X-Payment',
            'Content-Type' => 'application/json',
            'X-Payment-Accept' => implode(', ', $schemes),
        ];
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $accepts = [];
        foreach ($data['accepts'] ?? [] as $acceptData) {
            $accepts[] = PaymentRequirements::fromArray($acceptData);
        }

        return new self(
            x402Version: (int)($data['x402Version'] ?? $data['x402_version'] ?? 1),
            accepts: $accepts,
            error: $data['error'] ?? ''
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
            'x402Version' => $this->x402Version,
            'accepts' => array_map(fn(PaymentRequirements $req) => $req->toArray(), $this->accepts),
            'error' => $this->error,
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
