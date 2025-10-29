<?php

declare(strict_types=1);

namespace X402\Validation;

use X402\Exceptions\ValidationException;

/**
 * Validator for x402 protocol data.
 */
class Validator
{
    /**
     * Supported x402 network IDs.
     */
    public const SUPPORTED_NETWORKS = [
        // Ethereum
        'ethereum-mainnet',
        'ethereum-sepolia',
        'ethereum-holesky',
        
        // Base (Coinbase L2)
        'base-mainnet',
        'base-sepolia',
        
        // Optimism
        'optimism-mainnet',
        'optimism-sepolia',
        
        // Arbitrum
        'arbitrum-mainnet',
        'arbitrum-sepolia',
        
        // Polygon
        'polygon-mainnet',
        'polygon-amoy',
        
        // Solana
        'solana-mainnet',
        'solana-devnet',
        'solana-testnet',
    ];

    /**
     * Supported payment schemes.
     */
    public const SUPPORTED_SCHEMES = [
        'exact',
    ];

    /**
     * Validate Ethereum address format.
     *
     * @param string $address
     * @return bool
     */
    public static function isValidEthereumAddress(string $address): bool
    {
        return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }

    /**
     * Validate Solana address format (base58 encoded, 32-44 chars).
     *
     * @param string $address
     * @return bool
     */
    public static function isValidSolanaAddress(string $address): bool
    {
        // Solana addresses are base58 encoded public keys (32 bytes)
        // Base58 encoding results in 32-44 characters
        // Valid base58 characters: 123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz
        return (bool)preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
    }

    /**
     * Validate address format based on network type.
     *
     * @param string $address
     * @param string $network
     * @return bool
     */
    public static function isValidAddress(string $address, string $network): bool
    {
        if (self::isSvmNetwork($network)) {
            return self::isValidSolanaAddress($address);
        }
        return self::isValidEthereumAddress($address);
    }

    /**
     * Validate that a string represents a valid positive integer.
     *
     * @param string $value
     * @return bool
     */
    public static function isValidUintString(string $value): bool
    {
        if (!preg_match('/^\d+$/', $value)) {
            return false;
        }
        
        // Additional check: ensure no leading zeros unless it's just "0"
        if (strlen($value) > 1 && $value[0] === '0') {
            return false;
        }
        
        return true;
    }

    /**
     * Validate network ID is supported.
     *
     * @param string $network
     * @return bool
     */
    public static function isValidNetwork(string $network): bool
    {
        return in_array($network, self::SUPPORTED_NETWORKS, true);
    }

    /**
     * Determine if the payment scheme is supported by the library.
     */
    public static function isSupportedScheme(string $scheme): bool
    {
        return in_array($scheme, self::SUPPORTED_SCHEMES, true);
    }

    /**
     * Check if a network is SVM (Solana).
     *
     * @param string $network
     * @return bool
     */
    public static function isSvmNetwork(string $network): bool
    {
        return str_starts_with($network, 'solana-');
    }

    /**
     * Check if a network is EVM (Ethereum-based).
     *
     * @param string $network
     * @return bool
     */
    public static function isEvmNetwork(string $network): bool
    {
        return !self::isSvmNetwork($network);
    }

    /**
     * Validate payment requirements object.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public static function validatePaymentRequirements(array $data): void
    {
        $required = ['scheme', 'network', 'maxAmountRequired', 'resource', 'description', 'mimeType', 'payTo', 'maxTimeoutSeconds', 'asset'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) && !isset($data[self::toSnakeCase($field)])) {
                throw new ValidationException("Missing required field: $field");
            }
        }

        // Validate optional ID field (required by some facilitators)
        if (isset($data['id'])) {
            if (!is_string($data['id']) || trim($data['id']) === '') {
                throw new ValidationException("Payment ID must be a non-empty string");
            }
        }

        // Validate network
        $network = $data['network'] ?? '';
        if (!self::isValidNetwork($network)) {
            throw new ValidationException("Invalid network. Supported networks: " . implode(', ', self::SUPPORTED_NETWORKS));
        }

        $scheme = $data['scheme'] ?? '';
        if (!self::isSupportedScheme($scheme)) {
            throw new ValidationException("Unsupported payment scheme: {$scheme}");
        }

        $maxAmount = $data['maxAmountRequired'] ?? $data['max_amount_required'] ?? '';
        if (!self::isValidUintString($maxAmount)) {
            throw new ValidationException("maxAmountRequired must be a valid unsigned integer string");
        }

        $payTo = $data['payTo'] ?? $data['pay_to'] ?? '';
        if (!self::isValidAddress($payTo, $network)) {
            $addressType = self::isSvmNetwork($network) ? 'Solana' : 'Ethereum';
            throw new ValidationException("payTo must be a valid {$addressType} address");
        }

        $asset = $data['asset'] ?? '';
        if (!self::isValidAddress($asset, $network)) {
            $addressType = self::isSvmNetwork($network) ? 'SPL token' : 'ERC20 token';
            throw new ValidationException("asset must be a valid {$addressType} address");
        }

        $timeout = $data['maxTimeoutSeconds'] ?? $data['max_timeout_seconds'] ?? 0;
        if (!is_numeric($timeout) || (int)$timeout <= 0) {
            throw new ValidationException("maxTimeoutSeconds must be a positive integer");
        }
    }

    /**
     * Validate payment payload structure.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public static function validatePaymentPayload(array $data): void
    {
        if (!isset($data['x402Version']) && !isset($data['x402_version'])) {
            throw new ValidationException("Missing required field: x402Version");
        }

        if (!isset($data['scheme'])) {
            throw new ValidationException("Missing required field: scheme");
        }

        if (!isset($data['network'])) {
            throw new ValidationException("Missing required field: network");
        }

        if (!isset($data['payload'])) {
            throw new ValidationException("Missing required field: payload");
        }

        $scheme = $data['scheme'];
        $network = $data['network'];

        if (!self::isSupportedScheme($scheme)) {
            throw new ValidationException("Unsupported payment scheme: {$scheme}");
        }
        
        if ($scheme === 'exact') {
            if (self::isSvmNetwork($network)) {
                self::validateExactSvmPayload($data['payload']);
            } else {
                self::validateExactEvmPayload($data['payload']);
            }
        }
    }

    /**
     * Validate exact payment scheme payload for EVM.
     *
     * @param mixed $payload
     * @throws ValidationException
     */
    public static function validateExactEvmPayload(mixed $payload): void
    {
        if (!is_array($payload)) {
            throw new ValidationException("Exact EVM payload must be an array");
        }

        if (!isset($payload['signature'])) {
            throw new ValidationException("Missing required field in exact EVM payload: signature");
        }

        // Validate signature format (0x + 130 hex chars = 65 bytes)
        $signature = $payload['signature'];
        if (!preg_match('/^0x[a-fA-F0-9]{130}$/', $signature)) {
            throw new ValidationException("EVM signature must be a 65-byte hex string (0x + 130 hex characters)");
        }

        if (!isset($payload['authorization'])) {
            throw new ValidationException("Missing required field in exact EVM payload: authorization");
        }

        $auth = $payload['authorization'];
        if (!is_array($auth)) {
            throw new ValidationException("Authorization must be an array");
        }

        $required = ['from', 'to', 'value', 'validAfter', 'validBefore', 'nonce'];
        foreach ($required as $field) {
            if (!isset($auth[$field]) && !isset($auth[self::toSnakeCase($field)])) {
                throw new ValidationException("Missing required field in authorization: $field");
            }
        }

        $from = $auth['from'] ?? '';
        if (!self::isValidEthereumAddress($from)) {
            throw new ValidationException("authorization.from must be a valid Ethereum address");
        }

        $to = $auth['to'] ?? '';
        if (!self::isValidEthereumAddress($to)) {
            throw new ValidationException("authorization.to must be a valid Ethereum address");
        }

        $value = $auth['value'] ?? '';
        if (!self::isValidUintString($value)) {
            throw new ValidationException("authorization.value must be a valid unsigned integer string");
        }

        $validAfter = $auth['validAfter'] ?? $auth['valid_after'] ?? '';
        if (!self::isValidUintString($validAfter)) {
            throw new ValidationException("authorization.validAfter must be a valid unsigned integer string");
        }

        $validBefore = $auth['validBefore'] ?? $auth['valid_before'] ?? '';
        if (!self::isValidUintString($validBefore)) {
            throw new ValidationException("authorization.validBefore must be a valid unsigned integer string");
        }

        // Validate nonce format (0x + 64 hex chars = 32 bytes)
        $nonce = $auth['nonce'] ?? '';
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $nonce)) {
            throw new ValidationException("authorization.nonce must be a 32-byte hex string (0x + 64 hex characters)");
        }
    }

    /**
     * Convert camelCase to snake_case.
     *
     * @param string $input
     * @return string
     */
    private static function toSnakeCase(string $input): string
    {
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        if ($converted === null) {
            return strtolower($input);
        }

        return strtolower($converted);
    }

    /**
     * Validate exact payment scheme payload for Solana (SVM).
     *
     * @param mixed $payload
     * @throws ValidationException
     */
    public static function validateExactSvmPayload(mixed $payload): void
    {
        if (!is_array($payload)) {
            throw new ValidationException("Exact SVM payload must be an array");
        }

        if (!isset($payload['transaction'])) {
            throw new ValidationException("Missing required field in exact SVM payload: transaction");
        }

        $transaction = $payload['transaction'];

        if (!is_string($transaction)) {
            throw new ValidationException("Solana transaction must be a string");
        }

        if ($transaction === '') {
            throw new ValidationException("Solana transaction is empty");
        }

        // Validate base64 format
        if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $transaction)) {
            throw new ValidationException("Invalid base64-encoded Solana transaction");
        }

        // Validate the transaction can be base64 decoded
        $decoded = base64_decode($transaction, true);
        if ($decoded === false) {
            throw new ValidationException("Invalid base64-encoded Solana transaction");
        }

        // Basic length check - Solana transactions are typically 500-1232 bytes
        $length = strlen($decoded);
        if ($length < 100 || $length > 1500) {
            throw new ValidationException("Solana transaction has invalid length (expected 100-1500 bytes)");
        }
    }

    /**
     * Sanitize string input to prevent XSS.
     *
     * @param string $input
     * @return string
     */
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate and sanitize URL.
     *
     * @param string $url
     * @return string
     * @throws ValidationException
     */
    public static function sanitizeUrl(string $url): string
    {
        $sanitized = filter_var($url, FILTER_SANITIZE_URL);
        if ($sanitized === false || !filter_var($sanitized, FILTER_VALIDATE_URL)) {
            throw new ValidationException("Invalid URL format");
        }
        return $sanitized;
    }

    /**
     * Validate nonce is not reused (replay attack prevention).
     * 
     * This helper method checks if a nonce has been used before by calling
     * the provided callback function. The callback should query your 
     * database/cache to check if the nonce exists.
     * 
     * Example usage:
     * ```php
     * $isUnique = Validator::isNonceUnique($nonce, function($nonce) use ($redis) {
     *     return !$redis->exists("nonce:$nonce");
     * });
     * ```
     *
     * @param string $nonce The nonce to check (should be a hex string)
     * @param callable $checkCallback Callback that returns true if nonce is unique, false if already used
     * @return bool True if nonce is unique and can be used, false if already used
     * @throws ValidationException If nonce format is invalid
     */
    public static function isNonceUnique(string $nonce, callable $checkCallback): bool
    {
        // Validate nonce format (should be 32-byte hex string for EVM)
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $nonce)) {
            throw new ValidationException("Nonce must be a 32-byte hex string (0x + 64 hex characters)");
        }

        // Call the provided callback to check uniqueness
        // The callback should return true if nonce is unique
        return (bool) $checkCallback($nonce);
    }

    /**
     * Mark a nonce as used to prevent replay attacks.
     * 
     * This helper method stores the nonce using the provided callback function.
     * The callback should store the nonce in your database/cache with an appropriate
     * expiration time (e.g., validBefore timestamp).
     * 
     * Example usage:
     * ```php
     * Validator::markNonceAsUsed($nonce, $validBefore, function($nonce, $expiry) use ($redis) {
     *     $ttl = max(0, $expiry - time());
     *     $redis->setex("nonce:$nonce", $ttl, '1');
     * });
     * ```
     *
     * @param string $nonce The nonce to mark as used
     * @param int $validBefore Unix timestamp when the nonce expires
     * @param callable $storeCallback Callback to store the nonce (receives nonce and validBefore timestamp)
     * @throws ValidationException If nonce format is invalid
     */
    public static function markNonceAsUsed(string $nonce, int $validBefore, callable $storeCallback): void
    {
        // Validate nonce format (should be 32-byte hex string for EVM)
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $nonce)) {
            throw new ValidationException("Nonce must be a 32-byte hex string (0x + 64 hex characters)");
        }

        // Validate expiration time
        if ($validBefore <= 0) {
            throw new ValidationException("validBefore must be a positive timestamp");
        }

        // Call the provided callback to store the nonce
        $storeCallback($nonce, $validBefore);
    }
}
