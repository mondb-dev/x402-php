<?php

declare(strict_types=1);

namespace X402\Webhook;

use X402\Exceptions\ValidationException;

/**
 * Webhook handler for processing x402 facilitator webhooks.
 */
class WebhookHandler
{
    public function __construct(
        private readonly string $webhookSecret
    ) {
        if (empty($webhookSecret)) {
            throw new \InvalidArgumentException('Webhook secret cannot be empty');
        }
    }

    /**
     * Verify webhook signature.
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Signature from webhook header
     * @param string $algorithm Hashing algorithm (default: sha256)
     * @return bool True if signature is valid
     */
    public function verifySignature(
        string $payload,
        string $signature,
        string $algorithm = 'sha256'
    ): bool {
        $expected = hash_hmac($algorithm, $payload, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Handle incoming webhook and return event.
     *
     * @param string $payload Raw webhook payload (JSON)
     * @param string $signature Signature from header
     * @return WebhookEvent Parsed webhook event
     * @throws ValidationException If signature is invalid or payload is malformed
     */
    public function handleWebhook(string $payload, string $signature): WebhookEvent
    {
        // Verify signature
        if (!$this->verifySignature($payload, $signature)) {
            throw new ValidationException('Invalid webhook signature');
        }

        // Parse JSON
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException('Invalid JSON in webhook payload: ' . $e->getMessage());
        }

        if (!isset($data['event'])) {
            throw new ValidationException('Webhook payload missing event field');
        }

        $timestamp = isset($data['timestamp']) 
            ? new \DateTimeImmutable($data['timestamp'])
            : new \DateTimeImmutable();

        // Create appropriate event based on type
        return match($data['event']) {
            'payment.settled' => new PaymentSettledEvent($data['data'] ?? $data, $timestamp),
            'payment.failed' => new PaymentFailedEvent($data['data'] ?? $data, $timestamp),
            default => throw new ValidationException('Unknown webhook event type: ' . $data['event'])
        };
    }

    /**
     * Extract signature from HTTP headers.
     *
     * @param array<string, string|array<string>> $headers HTTP headers
     * @param string $headerName Header name containing signature (default: X-Webhook-Signature)
     * @return string|null Signature or null if not found
     */
    public function extractSignature(array $headers, string $headerName = 'X-Webhook-Signature'): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $headerName) === 0 || strcasecmp($key, 'HTTP_' . str_replace('-', '_', strtoupper($headerName))) === 0) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return null;
    }
}
