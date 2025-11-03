<?php

declare(strict_types=1);

namespace X402\Facilitator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
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
     * @param array<string, mixed>|int|null $httpOptions Additional Guzzle HTTP client options or timeout seconds
     */
    public function __construct(
        string $facilitatorUrl,
        array|int|null $httpOptions = null,
        ?string $apiKey = null
    ) {
        if (!filter_var($facilitatorUrl, FILTER_VALIDATE_URL)) {
            throw new FacilitatorException('Invalid facilitator base URL');
        }

        $scheme = parse_url($facilitatorUrl, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            throw new FacilitatorException('Facilitator URL must use HTTPS');
        }

        $normalizedUrl = rtrim($facilitatorUrl, '/');

        $options = [];

        if (is_int($httpOptions)) {
            $options['timeout'] = $httpOptions;
        } elseif (is_array($httpOptions)) {
            $options = $httpOptions;
        }

        if ($apiKey !== null) {
            $options['headers']['X-API-Key'] = $apiKey;
        }

        $defaultOptions = [
            'base_uri' => $normalizedUrl,
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 5,
            'headers' => array_merge(
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'x402-php/' . self::VERSION,
                ],
                $options['headers'] ?? []
            ),
            'http_errors' => true,
        ];

        unset($options['timeout'], $options['connect_timeout'], $options['headers']);

        $this->httpClient = new Client(array_replace_recursive($defaultOptions, $options));
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
     * Create a client for PayAI Facilitator (default).
     *
     * @param string|null $apiKey Optional API key
     * @param int $timeout Request timeout in seconds
     * @return self
     */
    public static function payai(?string $apiKey = null, int $timeout = 30): self
    {
        $options = ['timeout' => $timeout];

        if ($apiKey !== null) {
            $options['headers'] = ['X-API-Key' => $apiKey];
        }

        return new self(
            'https://facilitator.payai.network',
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

        if ($baseUrl === false || $baseUrl === '') {
            return null;
        }

        $options = [
            'timeout' => (int)(getenv('FACILITATOR_TIMEOUT') ?: 30)
        ];

        $apiKey = getenv('FACILITATOR_API_KEY');
        if ($apiKey !== false && $apiKey !== '') {
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
        if (trim($paymentHeader) === '') {
            throw new FacilitatorException('Invalid payment header');
        }

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
        if (trim($paymentHeader) === '') {
            throw new FacilitatorException('Invalid payment header');
        }

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
     * SECURITY: This method sanitizes error responses to prevent information leakage.
     * Detailed errors are logged but not exposed to clients.
     *
     * @param GuzzleException $e
     * @param string $action
     * @return never
     * @throws FacilitatorException
     */
    private function handleGuzzleException(GuzzleException $e, string $action): never
    {
        // Default safe error message
        $errorMessage = "Failed to {$action}";
        $errorCode = 'facilitator_error';
        
        // Log detailed error for debugging (should be configured to use PSR-3 logger in production)
        error_log(sprintf(
            '[x402-php] Facilitator error [%s]: %s',
            $action,
            $e->getMessage()
        ));
        
        // Parse HTTP response for safe error categorization
        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            
            if ($response !== null) {
                $statusCode = $response->getStatusCode();
                
                // Only expose safe, high-level error categories (no internal details)
                $errorMessage = match (true) {
                    $statusCode === 400 => "Invalid payment request",
                    $statusCode === 401, $statusCode === 403 => "Authentication failed",
                    $statusCode === 404 => "Facilitator endpoint not found",
                    $statusCode === 429 => "Rate limit exceeded",
                    $statusCode >= 500 => "Facilitator service unavailable",
                    default => "Payment verification failed"
                };
                
                $errorCode = "facilitator_http_{$statusCode}";
                
                // Log response body for debugging (NOT exposed to client)
                $body = (string)$response->getBody();
                if ($body !== '') {
                    error_log(sprintf(
                        '[x402-php] Facilitator response [%d]: %s',
                        $statusCode,
                        substr($body, 0, 500) // Limit log size
                    ));
                }
            }
        }

        throw new FacilitatorException($errorMessage, previous: $e);
    }
}
