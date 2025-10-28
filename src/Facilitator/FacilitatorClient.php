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
use X402\Types\SupportedConfiguration;

/**
 * Client for communicating with x402 facilitator servers.
 */
class FacilitatorClient
{
    private Client $httpClient;
    private const VERSION = '1.0.0';

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
                'User-Agent' => 'x402-php/' . self::VERSION,
            ],
        ];

        $this->httpClient = new Client(array_merge($defaultOptions, $httpOptions));
    }

    /**
     * Create a client for Coinbase Facilitator.
     *
     * @param string|null $apiKey Optional API key (required for production)
     * @param int $timeout Request timeout in seconds
     * @return self
     */
    public static function coinbase(?string $apiKey = null, int $timeout = 30): self
    {
        $options = ['timeout' => $timeout];
        
        if ($apiKey !== null) {
            $options['headers'] = ['X-API-Key' => $apiKey];
        }

        return new self(
            'https://facilitator.coinbase.com/api/v1',
            $options
        );
    }

    /**
     * Create a client for a self-hosted facilitator.
     *
     * @param string $baseUrl Your facilitator's base URL
     * @param string|null $apiKey Optional API key
     * @param int $timeout Request timeout in seconds
     * @return self
     */
    public static function selfHosted(
        string $baseUrl,
        ?string $apiKey = null,
        int $timeout = 30
    ): self {
        $options = ['timeout' => $timeout];
        
        if ($apiKey !== null) {
            $options['headers'] = ['X-API-Key' => $apiKey];
        }

        return new self(rtrim($baseUrl, '/'), $options);
    }

    /**
     * Create a client from environment variables.
     *
     * Environment variables:
     * - FACILITATOR_BASE_URL (required)
     * - FACILITATOR_API_KEY (optional)
     * - FACILITATOR_TIMEOUT (optional, defaults to 30)
     *
     * @return self|null Returns null if FACILITATOR_BASE_URL is not set
     */
    public static function fromEnvironment(): ?self
    {
        $baseUrl = getenv('FACILITATOR_BASE_URL');
        
        if (!$baseUrl || $baseUrl === false) {
            return null;
        }

        $options = [
            'timeout' => (int)(getenv('FACILITATOR_TIMEOUT') ?: 30)
        ];

        $apiKey = getenv('FACILITATOR_API_KEY');
        if ($apiKey && $apiKey !== false) {
            $options['headers'] = ['X-API-Key' => $apiKey];
        }

        return new self($baseUrl, $options);
    }

    /**
     * Verify a payment with the facilitator.
     *
     * @param string $paymentHeader Base64 encoded payment header
     * @param PaymentRequirements $requirements Payment requirements
     * @return VerifyResponse
     * @throws FacilitatorException
     */
    public function verify(
        string $paymentHeader,
        PaymentRequirements $requirements
    ): VerifyResponse {
        $payload = [
            'x402Version' => 1,
            'paymentHeader' => $paymentHeader,
            'paymentRequirements' => $requirements->toArray(),
        ];

        try {
            $response = $this->httpClient->post('/verify', [
                'json' => $payload,
                'headers' => [
                    'X-Request-ID' => $this->generateRequestId(),
                ],
            ]);

            $body = (string)$response->getBody();
            $data = Encoder::decodeJson($body);

            if (!is_array($data)) {
                throw new FacilitatorException("Invalid response from facilitator");
            }

            return VerifyResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e, 'verify payment');
        }
    }

    /**
     * Settle a payment with the facilitator.
     *
     * @param string $paymentHeader Base64 encoded payment header
     * @param PaymentRequirements $requirements Payment requirements
     * @return SettleResponse
     * @throws FacilitatorException
     */
    public function settle(
        string $paymentHeader,
        PaymentRequirements $requirements
    ): SettleResponse {
        $payload = [
            'x402Version' => 1,
            'paymentHeader' => $paymentHeader,
            'paymentRequirements' => $requirements->toArray(),
        ];

        try {
            $response = $this->httpClient->post('/settle', [
                'json' => $payload,
                'headers' => [
                    'X-Request-ID' => $this->generateRequestId(),
                ],
            ]);

            $body = (string)$response->getBody();
            $data = Encoder::decodeJson($body);

            if (!is_array($data)) {
                throw new FacilitatorException("Invalid response from facilitator");
            }

            return SettleResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e, 'settle payment');
        }
    }

    /**
     * Get supported schemes and networks from the facilitator.
     *
     * @return SupportedConfiguration
     * @throws FacilitatorException
     */
    public function getSupported(): SupportedConfiguration
    {
        try {
            $response = $this->httpClient->get('/supported', [
                'headers' => [
                    'X-Request-ID' => $this->generateRequestId(),
                ],
            ]);
            
            $body = (string)$response->getBody();
            $data = Encoder::decodeJson($body);

            if (!is_array($data)) {
                throw new FacilitatorException("Invalid response from facilitator");
            }

            return SupportedConfiguration::fromArray($data);
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e, 'get supported configurations');
        }
    }

    /**
     * Generate unique request ID for debugging.
     *
     * @return string
     */
    private function generateRequestId(): string
    {
        return sprintf(
            'x402-php-%s-%s',
            time(),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Handle Guzzle exceptions and parse error responses.
     *
     * @param GuzzleException $e
     * @param string $action
     * @return never
     * @throws FacilitatorException
     */
    private function handleGuzzleException(GuzzleException $e, string $action): never
    {
        $errorMessage = "Failed to {$action}: " . $e->getMessage();
        
        // Try to parse error response from Coinbase Facilitator
        if (method_exists($e, 'hasResponse') && $e->hasResponse()) {
            $response = $e->getResponse();
            $body = (string)$response->getBody();
            
            try {
                $errorData = Encoder::decodeJson($body);
                
                if (is_array($errorData) && isset($errorData['error'])) {
                    if (is_array($errorData['error'])) {
                        // Coinbase format: { error: { code, message, details } }
                        $errorMessage = "Failed to {$action}: " . ($errorData['error']['message'] ?? 'Unknown error');
                    } else {
                        // Simple format: { error: "message" }
                        $errorMessage = "Failed to {$action}: " . $errorData['error'];
                    }
                }
            } catch (\Exception $jsonException) {
                // Ignore JSON parsing errors, use original message
            }
        }

        throw new FacilitatorException($errorMessage, previous: $e);
    }
}
