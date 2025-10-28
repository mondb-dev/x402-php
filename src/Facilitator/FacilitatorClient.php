<?php

declare(strict_types=1);

namespace X402\Facilitator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use X402\Encoding\Encoder;
use X402\Exceptions\FacilitatorException;
use X402\Types\PaymentRequirements;
use X402\Types\SettleResponse;
use X402\Types\VerifyResponse;

/**
 * Client for communicating with x402 facilitator servers.
 */
class FacilitatorClient
{
    private Client $httpClient;

    /**
     * @param string $facilitatorUrl Base URL of the facilitator server
     * @param array<string, mixed> $httpOptions Additional Guzzle HTTP client options
     */
    public function __construct(
        private readonly string $facilitatorUrl,
        array $httpOptions = []
    ) {
        $defaultOptions = [
            'base_uri' => rtrim($facilitatorUrl, '/'),
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        $this->httpClient = new Client(array_merge($defaultOptions, $httpOptions));
    }

    /**
     * Verify a payment with the facilitator.
     *
     * @param string $paymentHeader Base64 encoded payment payload
     * @param PaymentRequirements $requirements Payment requirements
     * @param int $x402Version Protocol version (default: 1)
     * @return VerifyResponse
     * @throws FacilitatorException
     */
    public function verify(
        string $paymentHeader,
        PaymentRequirements $requirements,
        int $x402Version = 1
    ): VerifyResponse {
        $payload = [
            'x402Version' => $x402Version,
            'paymentHeader' => $paymentHeader,
            'paymentRequirements' => $requirements->toArray(),
        ];

        try {
            $response = $this->httpClient->post('/verify', [
                'json' => $payload,
            ]);

            $body = (string)$response->getBody();
            $data = Encoder::decodeJson($body);

            if (!is_array($data)) {
                throw new FacilitatorException("Invalid response from facilitator");
            }

            return VerifyResponse::fromArray($data);
        } catch (GuzzleException $e) {
            throw new FacilitatorException(
                "Failed to verify payment: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Settle a payment with the facilitator.
     *
     * @param string $paymentHeader Base64 encoded payment payload
     * @param PaymentRequirements $requirements Payment requirements
     * @param int $x402Version Protocol version (default: 1)
     * @return SettleResponse
     * @throws FacilitatorException
     */
    public function settle(
        string $paymentHeader,
        PaymentRequirements $requirements,
        int $x402Version = 1
    ): SettleResponse {
        $payload = [
            'x402Version' => $x402Version,
            'paymentHeader' => $paymentHeader,
            'paymentRequirements' => $requirements->toArray(),
        ];

        try {
            $response = $this->httpClient->post('/settle', [
                'json' => $payload,
            ]);

            $body = (string)$response->getBody();
            $data = Encoder::decodeJson($body);

            if (!is_array($data)) {
                throw new FacilitatorException("Invalid response from facilitator");
            }

            return SettleResponse::fromArray($data);
        } catch (GuzzleException $e) {
            throw new FacilitatorException(
                "Failed to settle payment: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get supported schemes and networks from the facilitator.
     *
     * @return array<string, mixed>
     * @throws FacilitatorException
     */
    public function getSupported(): array
    {
        try {
            $response = $this->httpClient->get('/supported');
            $body = (string)$response->getBody();
            $data = Encoder::decodeJson($body);

            if (!is_array($data)) {
                throw new FacilitatorException("Invalid response from facilitator");
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new FacilitatorException(
                "Failed to get supported schemes: " . $e->getMessage(),
                previous: $e
            );
        }
    }
}
