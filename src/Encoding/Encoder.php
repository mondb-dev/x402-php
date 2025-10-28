<?php

declare(strict_types=1);

namespace X402\Encoding;

use X402\Exceptions\ValidationException;
use X402\Types\PaymentPayload;
use X402\Validation\Validator;

/**
 * Encoder/decoder for x402 protocol data.
 */
class Encoder
{
    /**
     * Encode payment payload to base64 JSON string for X-PAYMENT header.
     *
     * @param PaymentPayload $payload
     * @return string
     * @throws ValidationException
     */
    public static function encodePaymentHeader(PaymentPayload $payload): string
    {
        $json = json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
        if ($json === false) {
            throw new ValidationException("Failed to encode payment payload to JSON");
        }
        
        return base64_encode($json);
    }

    /**
     * Decode payment header from base64 JSON string.
     *
     * @param string $header
     * @return PaymentPayload
     * @throws ValidationException
     */
    public static function decodePaymentHeader(string $header): PaymentPayload
    {
        $decoded = base64_decode($header, true);
        if ($decoded === false) {
            throw new ValidationException("Invalid base64 encoding in payment header");
        }

        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException("Invalid JSON in payment header: " . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new ValidationException("Payment header must decode to an array");
        }

        Validator::validatePaymentPayload($data);

        return PaymentPayload::fromArray($data);
    }

    /**
     * Encode data to JSON.
     *
     * @param mixed $data
     * @return string
     * @throws ValidationException
     */
    public static function encodeJson(mixed $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new ValidationException("Failed to encode JSON: " . $e->getMessage());
        }
    }

    /**
     * Decode JSON data.
     *
     * @param string $json
     * @return mixed
     * @throws ValidationException
     */
    public static function decodeJson(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException("Failed to decode JSON: " . $e->getMessage());
        }
    }
}
