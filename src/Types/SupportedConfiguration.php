<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;

/**
 * Supported configuration from facilitator.
 */
class SupportedConfiguration implements JsonSerializable
{
    /**
     * @param string $version Facilitator API version
     * @param array<NetworkInfo> $networks Supported networks
     * @param array<string> $schemes Supported payment schemes
     * @param array<string, bool> $features Supported features
     */
    public function __construct(
        public readonly string $version,
        public readonly array $networks,
        public readonly array $schemes,
        public readonly array $features = []
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
        $networks = [];
        foreach ($data['networks'] ?? [] as $networkData) {
            $networks[] = NetworkInfo::fromArray($networkData);
        }

        return new self(
            version: $data['version'] ?? '1.0.0',
            networks: $networks,
            schemes: $data['schemes'] ?? ['exact'],
            features: $data['features'] ?? []
        );
    }

    /**
     * Check if a network is supported.
     *
     * @param string $networkId
     * @return bool
     */
    public function supportsNetwork(string $networkId): bool
    {
        foreach ($this->networks as $network) {
            if ($network->id === $networkId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get network info by ID.
     *
     * @param string $networkId
     * @return NetworkInfo|null
     */
    public function getNetwork(string $networkId): ?NetworkInfo
    {
        foreach ($this->networks as $network) {
            if ($network->id === $networkId) {
                return $network;
            }
        }
        return null;
    }

    /**
     * Check if a scheme is supported.
     *
     * @param string $scheme
     * @return bool
     */
    public function supportsScheme(string $scheme): bool
    {
        return in_array($scheme, $this->schemes, true);
    }

    /**
     * Check if a feature is supported.
     *
     * @param string $feature
     * @return bool
     */
    public function supportsFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'networks' => array_map(fn($n) => $n->toArray(), $this->networks),
            'schemes' => $this->schemes,
            'features' => $this->features,
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
