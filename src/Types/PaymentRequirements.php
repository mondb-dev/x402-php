<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Payment requirements that a resource server accepts for a specific resource.
 */
class PaymentRequirements implements JsonSerializable
{
    /**
     * @param string $scheme Scheme of the payment protocol to use (e.g., "exact")
     * @param string $network Network of the blockchain to send payment on
     * @param string $maxAmountRequired Maximum amount required to pay for the resource in atomic units
     * @param string $resource URL of resource to pay for
     * @param string $description Description of the resource
     * @param string $mimeType MIME type of the resource response
     * @param string $payTo Address to pay value to
     * @param int $maxTimeoutSeconds Maximum time in seconds for the resource server to respond
     * @param string $asset Address of the EIP-3009 compliant ERC20 contract
     * @param array<string, mixed>|null $outputSchema Output schema of the resource response
     * @param array<string, mixed>|null $extra Extra information about the payment details specific to the scheme
     */
    public function __construct(
        public readonly string $scheme,
        public readonly string $network,
        public readonly string $maxAmountRequired,
        public readonly string $resource,
        public readonly string $description,
        public readonly string $mimeType,
        public readonly string $payTo,
        public readonly int $maxTimeoutSeconds,
        public readonly string $asset,
        public readonly ?array $outputSchema = null,
        public readonly ?array $extra = null
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
            scheme: $data['scheme'] ?? $data['scheme'] ?? '',
            network: $data['network'] ?? $data['network'] ?? '',
            maxAmountRequired: $data['maxAmountRequired'] ?? $data['max_amount_required'] ?? '',
            resource: $data['resource'] ?? $data['resource'] ?? '',
            description: $data['description'] ?? $data['description'] ?? '',
            mimeType: $data['mimeType'] ?? $data['mime_type'] ?? '',
            payTo: $data['payTo'] ?? $data['pay_to'] ?? '',
            maxTimeoutSeconds: (int)($data['maxTimeoutSeconds'] ?? $data['max_timeout_seconds'] ?? 0),
            asset: $data['asset'] ?? $data['asset'] ?? '',
            outputSchema: $data['outputSchema'] ?? $data['output_schema'] ?? null,
            extra: $data['extra'] ?? $data['extra'] ?? null
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
            'scheme' => $this->scheme,
            'network' => $this->network,
            'maxAmountRequired' => $this->maxAmountRequired,
            'resource' => $this->resource,
            'description' => $this->description,
            'mimeType' => $this->mimeType,
            'payTo' => $this->payTo,
            'maxTimeoutSeconds' => $this->maxTimeoutSeconds,
            'asset' => $this->asset,
        ];

        if ($this->outputSchema !== null) {
            $result['outputSchema'] = $this->outputSchema;
        }

        if ($this->extra !== null) {
            $result['extra'] = $this->extra;
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
