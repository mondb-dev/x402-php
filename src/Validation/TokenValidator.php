<?php

declare(strict_types=1);

namespace X402\Validation;

use X402\Exceptions\ValidationException;

/**
 * Validator for EIP-712 token domain parameters.
 * 
 * This validator ensures that EIP-712 domain parameters match known token contracts
 * to prevent signature verification attacks where incorrect domain parameters are provided.
 */
class TokenValidator
{
    /**
     * Known token contracts with their EIP-712 domain parameters.
     * 
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const KNOWN_TOKENS = [
        'base-mainnet' => [
            '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913' => [
                'name' => 'USD Coin',
                'version' => '2',
                'symbol' => 'USDC',
                'decimals' => 6
            ]
        ],
        'base-sepolia' => [
            '0x036cbd53842c5426634e7929541ec2318f3dcf7e' => [
                'name' => 'USD Coin',
                'version' => '2',
                'symbol' => 'USDC',
                'decimals' => 6
            ]
        ],
        'ethereum-mainnet' => [
            '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48' => [
                'name' => 'USD Coin',
                'version' => '2',
                'symbol' => 'USDC',
                'decimals' => 6
            ]
        ],
        'ethereum-sepolia' => [
            '0x1c7d4b196cb0c7b01d743fbc6116a902379c7238' => [
                'name' => 'USD Coin',
                'version' => '2',
                'symbol' => 'USDC',
                'decimals' => 6
            ]
        ],
    ];

    /**
     * Validate EIP-712 domain parameters against known token.
     * 
     * For known tokens, validates that the name and version match the expected values.
     * For unknown tokens, returns true (facilitator will validate).
     *
     * @param string $network Network ID
     * @param string $asset Token contract address
     * @param array<string, mixed> $extra Extra parameters containing domain info
     * @return bool True if valid
     * @throws ValidationException If domain parameters don't match expected values
     */
    public static function validateEIP712Domain(
        string $network,
        string $asset,
        array $extra
    ): bool {
        $asset = strtolower($asset);
        
        // Unknown token - facilitator will validate
        if (!isset(self::KNOWN_TOKENS[$network][$asset])) {
            // Still require name and version fields
            if (!isset($extra['name']) || !isset($extra['version'])) {
                throw new ValidationException(
                    "EIP-712 domain requires 'name' and 'version' in extra field"
                );
            }
            return true;
        }

        $expected = self::KNOWN_TOKENS[$network][$asset];
        
        if (!isset($extra['name']) || !isset($extra['version'])) {
            throw new ValidationException(
                "EIP-712 domain requires 'name' and 'version' in extra field"
            );
        }

        if ($extra['name'] !== $expected['name']) {
            throw new ValidationException(
                "Token name mismatch for {$asset}: expected '{$expected['name']}', got '{$extra['name']}'"
            );
        }

        if ($extra['version'] !== $expected['version']) {
            throw new ValidationException(
                "Token version mismatch for {$asset}: expected '{$expected['version']}', got '{$extra['version']}'"
            );
        }

        return true;
    }

    /**
     * Get known token information if available.
     *
     * @param string $network Network ID
     * @param string $asset Token contract address
     * @return array<string, mixed>|null Token info or null if unknown
     */
    public static function getKnownToken(string $network, string $asset): ?array
    {
        $asset = strtolower($asset);
        return self::KNOWN_TOKENS[$network][$asset] ?? null;
    }

    /**
     * Check if a token is known.
     *
     * @param string $network Network ID
     * @param string $asset Token contract address
     * @return bool True if token is known
     */
    public static function isKnownToken(string $network, string $asset): bool
    {
        $asset = strtolower($asset);
        return isset(self::KNOWN_TOKENS[$network][$asset]);
    }
}
