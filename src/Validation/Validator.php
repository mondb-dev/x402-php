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

        $maxAmount = $data['maxAmountRequired'] ?? $data['max_amount_required'] ?? '';
        if (!self::isValidUintString($maxAmount)) {
            throw new ValidationException("maxAmountRequired must be a valid unsigned integer string");
        }

        $payTo = $data['payTo'] ?? $data['pay_to'] ?? '';
        if (!self::isValidEthereumAddress($payTo)) {
            throw new ValidationException("payTo must be a valid Ethereum address");
        }

        $asset = $data['asset'] ?? '';
        if (!self::isValidEthereumAddress($asset)) {
            throw new ValidationException("asset must be a valid Ethereum address");
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
        if ($scheme === 'exact') {
            self::validateExactPayload($data['payload']);
        }
    }

    /**
     * Validate exact payment scheme payload.
     *
     * @param mixed $payload
     * @throws ValidationException
     */
    public static function validateExactPayload(mixed $payload): void
    {
        if (!is_array($payload)) {
            throw new ValidationException("Exact payload must be an array");
        }

        if (!isset($payload['signature'])) {
            throw new ValidationException("Missing required field in exact payload: signature");
        }

        if (!isset($payload['authorization'])) {
            throw new ValidationException("Missing required field in exact payload: authorization");
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
    }

    /**
     * Convert camelCase to snake_case.
     *
     * @param string $input
     * @return string
     */
    private static function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
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
}
