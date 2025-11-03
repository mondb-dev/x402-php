<?php

declare(strict_types=1);

namespace X402\Types;

use JsonSerializable;
use RuntimeException;

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
     * Get the HTTP status code for the response.
     */
    public function getStatusCode(): int
    {
        return 402;
    }

    /**
     * Send headers and body for a 402 Payment Required response.
     *
     * This helper ensures headers are emitted before the HTTP status code so
     * that PHP does not downgrade the response to 401 when WWW-Authenticate is
     * present.
     *
     * @param callable(string, string): void|null $headerSender Callback used to send headers
     * @param callable(int): void|null $statusSender Callback used to set the HTTP status code
     * @param callable(string): void|null $bodySender Callback used to output the body
     */
    public function send(
        ?callable $headerSender = null,
        ?callable $statusSender = null,
        ?callable $bodySender = null
    ): void {
        $headerSender ??= static function (string $name, string $value): void {
            header("{$name}: {$value}", replace: true);
        };

        $statusSender ??= static function (int $statusCode): void {
            http_response_code($statusCode);
        };

        $bodySender ??= static function (string $body): void {
            echo $body;
        };

        foreach ($this->getHeaders() as $name => $value) {
            $headerSender($name, $value);
        }

        $statusSender($this->getStatusCode());

        $body = json_encode($this);
        if ($body === false) {
            throw new RuntimeException(
                'Failed to encode payment required response: ' . json_last_error_msg()
            );
        }

        $bodySender($body);
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
