<?php

declare(strict_types=1);

namespace X402\Middleware;

use X402\Encoding\Encoder;
use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Facilitator\FacilitatorClient;
use X402\Types\PaymentPayload;
use X402\Types\PaymentRequiredResponse;
use X402\Types\PaymentRequirements;
use X402\Validation\Validator;

/**
 * Payment handler for x402 protocol.
 */
class PaymentHandler
{
    private const HEADER_PAYMENT = 'X-Payment';
    private const HEADER_PAYMENT_RESPONSE = 'X-Payment-Response';
    private const X402_VERSION = 1;

    /**
     * @param FacilitatorClient|null $facilitator Optional facilitator client for verification/settlement
     * @param bool $autoSettle Whether to automatically settle payments (default: true)
     */
    public function __construct(
        private readonly ?FacilitatorClient $facilitator = null,
        private readonly bool $autoSettle = true
    ) {
    }

    /**
     * Create payment requirements for a resource.
     *
     * @param string $payTo Address to receive payment
     * @param string $amount Amount in atomic units (e.g., "1000000" for USDC)
     * @param string $resource Resource URL
     * @param string $description Resource description
     * @param string $asset ERC20 token contract address
     * @param string $network Network ID (e.g., "base-sepolia", "base-mainnet")
     * @param string $scheme Payment scheme (default: "exact")
     * @param int $timeout Maximum timeout in seconds (default: 300)
     * @param string $mimeType Response MIME type (default: "application/json")
     * @param array<string, mixed>|null $extra Extra scheme-specific data
     * @return PaymentRequirements
     */
    public function createPaymentRequirements(
        string $payTo,
        string $amount,
        string $resource,
        string $description,
        string $asset,
        string $network = 'base-sepolia',
        string $scheme = 'exact',
        int $timeout = 300,
        string $mimeType = 'application/json',
        ?array $extra = null
    ): PaymentRequirements {
        // Validate inputs
        if (!Validator::isValidEthereumAddress($payTo)) {
            throw new ValidationException("Invalid payTo address");
        }

        if (!Validator::isValidEthereumAddress($asset)) {
            throw new ValidationException("Invalid asset address");
        }

        if (!Validator::isValidUintString($amount)) {
            throw new ValidationException("Invalid amount format");
        }

        return new PaymentRequirements(
            scheme: $scheme,
            network: $network,
            maxAmountRequired: $amount,
            resource: $resource,
            description: $description,
            mimeType: $mimeType,
            payTo: $payTo,
            maxTimeoutSeconds: $timeout,
            asset: $asset,
            extra: $extra
        );
    }

    /**
     * Create a 402 Payment Required response.
     *
     * @param PaymentRequirements $requirements Payment requirements
     * @param string $error Optional error message
     * @return PaymentRequiredResponse
     */
    public function createPaymentRequiredResponse(
        PaymentRequirements $requirements,
        string $error = ''
    ): PaymentRequiredResponse {
        return new PaymentRequiredResponse(
            x402Version: self::X402_VERSION,
            accepts: [$requirements],
            error: $error
        );
    }

    /**
     * Extract payment header from HTTP headers.
     *
     * @param array<string, string|array<string>> $headers HTTP headers
     * @return string|null Payment header value or null if not present
     */
    public function extractPaymentHeader(array $headers): ?string
    {
        // Check both standard and lowercase versions
        foreach ([self::HEADER_PAYMENT, strtolower(self::HEADER_PAYMENT)] as $key) {
            if (isset($headers[$key])) {
                $value = $headers[$key];
                return is_array($value) ? $value[0] : $value;
            }
        }

        return null;
    }

    /**
     * Verify payment from header.
     *
     * @param string $paymentHeader Base64 encoded payment payload
     * @param PaymentRequirements $requirements Payment requirements
     * @return PaymentPayload Validated payment payload
     * @throws ValidationException
     * @throws PaymentRequiredException
     */
    public function verifyPayment(string $paymentHeader, PaymentRequirements $requirements): PaymentPayload
    {
        // Decode payment header
        try {
            $payload = Encoder::decodePaymentHeader($paymentHeader);
        } catch (ValidationException $e) {
            throw new PaymentRequiredException("Invalid payment header: " . $e->getMessage());
        }

        // Basic validation
        if ($payload->scheme !== $requirements->scheme) {
            throw new PaymentRequiredException("Payment scheme mismatch");
        }

        if ($payload->network !== $requirements->network) {
            throw new PaymentRequiredException("Payment network mismatch");
        }

        // Use facilitator for verification if available
        if ($this->facilitator !== null) {
            $verifyResponse = $this->facilitator->verify($paymentHeader, $requirements, self::X402_VERSION);
            
            if (!$verifyResponse->isValid) {
                throw new PaymentRequiredException(
                    "Payment verification failed: " . ($verifyResponse->invalidReason ?? 'Unknown reason')
                );
            }
        }

        return $payload;
    }

    /**
     * Settle payment.
     *
     * @param string $paymentHeader Base64 encoded payment payload
     * @param PaymentRequirements $requirements Payment requirements
     * @return array<string, mixed> Settlement response data
     * @throws ValidationException
     */
    public function settlePayment(string $paymentHeader, PaymentRequirements $requirements): array
    {
        if ($this->facilitator === null) {
            throw new ValidationException("Facilitator required for payment settlement");
        }

        $settleResponse = $this->facilitator->settle($paymentHeader, $requirements, self::X402_VERSION);

        if (!$settleResponse->success) {
            throw new ValidationException("Payment settlement failed: " . ($settleResponse->error ?? 'Unknown error'));
        }

        return $settleResponse->toArray();
    }

    /**
     * Process payment for a request.
     *
     * @param array<string, string|array<string>> $headers HTTP request headers
     * @param PaymentRequirements $requirements Payment requirements
     * @return array{verified: bool, payload: PaymentPayload|null, settlement: array<string, mixed>|null}
     */
    public function processPayment(array $headers, PaymentRequirements $requirements): array
    {
        $paymentHeader = $this->extractPaymentHeader($headers);

        if ($paymentHeader === null) {
            return [
                'verified' => false,
                'payload' => null,
                'settlement' => null,
            ];
        }

        try {
            $payload = $this->verifyPayment($paymentHeader, $requirements);
            
            $settlement = null;
            if ($this->autoSettle && $this->facilitator !== null) {
                $settlement = $this->settlePayment($paymentHeader, $requirements);
            }

            return [
                'verified' => true,
                'payload' => $payload,
                'settlement' => $settlement,
            ];
        } catch (PaymentRequiredException $e) {
            return [
                'verified' => false,
                'payload' => null,
                'settlement' => null,
            ];
        }
    }

    /**
     * Get payment response header value.
     *
     * @param array<string, mixed> $settlementData Settlement response data
     * @return string Base64 encoded JSON settlement data
     */
    public function createPaymentResponseHeader(array $settlementData): string
    {
        $json = Encoder::encodeJson($settlementData);
        return base64_encode($json);
    }

    /**
     * Get the payment response header name.
     *
     * @return string
     */
    public function getPaymentResponseHeaderName(): string
    {
        return self::HEADER_PAYMENT_RESPONSE;
    }
}
