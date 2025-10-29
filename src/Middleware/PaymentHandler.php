<?php

declare(strict_types=1);

namespace X402\Middleware;

use X402\Encoding\Encoder;
use X402\Exceptions\ErrorCodes;
use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Facilitator\FacilitatorClient;
use X402\Exceptions\FacilitatorException;
use X402\Types\ExactPaymentPayload;
use X402\Types\ExactSvmPayload;
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
    
    // Default timing buffer for different network types
    // EVM L2s (Base, Optimism, Arbitrum): ~2s blocks = 6s for 3 blocks
    // Ethereum mainnet: ~12s blocks = 36s for 3 blocks
    // Solana: ~0.4s slots = 2s for 5 slots
    private const DEFAULT_BUFFER_SECONDS = 6;

    /**
     * @param FacilitatorClient|null $facilitator Optional facilitator client for verification/settlement
     * @param bool $autoSettle Whether to automatically settle payments (default: true)
     * @param int $validBeforeBufferSeconds Buffer time in seconds to account for block confirmation delays (default: 6)
     */
    public function __construct(
        private readonly ?FacilitatorClient $facilitator = null,
        private readonly bool $autoSettle = true,
        private readonly int $validBeforeBufferSeconds = self::DEFAULT_BUFFER_SECONDS
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
     * @param string|null $id Optional unique identifier (required by some facilitators like Coinbase)
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
        ?array $extra = null,
        ?string $id = null
    ): PaymentRequirements {
        if (!Validator::isSupportedScheme($scheme)) {
            throw new ValidationException("Unsupported payment scheme: {$scheme}");
        }

        if (!Validator::isValidNetwork($network)) {
            throw new ValidationException(
                'Invalid network. Supported networks: ' . implode(', ', Validator::SUPPORTED_NETWORKS)
            );
        }

        // Validate inputs
        if (!Validator::isValidAddress($payTo, $network)) {
            $addressType = Validator::isSvmNetwork($network) ? 'Solana' : 'Ethereum';
            throw new ValidationException("Invalid payTo {$addressType} address");
        }

        if (!Validator::isValidAddress($asset, $network)) {
            $addressType = Validator::isSvmNetwork($network) ? 'SPL token' : 'ERC20 token';
            throw new ValidationException("Invalid asset {$addressType} address");
        }

        if (!Validator::isValidUintString($amount)) {
            throw new ValidationException("Invalid amount format");
        }

        // For exact scheme on EVM networks, extra must contain name and version
        if ($scheme === 'exact' && Validator::isEvmNetwork($network)) {
            if ($extra === null || !isset($extra['name']) || !isset($extra['version'])) {
                throw new ValidationException(
                    "For exact scheme on EVM networks, extra must contain 'name' and 'version' fields for EIP-712 signature verification"
                );
            }
        }

        // For exact scheme on SVM networks, extra should contain feePayer
        if ($scheme === 'exact' && Validator::isSvmNetwork($network)) {
            if ($extra !== null && isset($extra['feePayer'])) {
                if (!Validator::isValidSolanaAddress($extra['feePayer'])) {
                    throw new ValidationException("feePayer in extra must be a valid Solana address");
                }
            }
        }

        $sanitizedResource = Validator::sanitizeUrl($resource);
        $sanitizedDescription = Validator::sanitizeString($description);

        return new PaymentRequirements(
            id: $id,
            scheme: $scheme,
            network: $network,
            maxAmountRequired: $amount,
            resource: $sanitizedResource,
            description: $sanitizedDescription,
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
     * @param array<string, string|array<int, string>|null> $headers HTTP headers
     * @return string|null Payment header value or null if not present
     */
    public function extractPaymentHeader(array $headers): ?string
    {
        // Check both standard and lowercase versions
        foreach ($headers as $key => $value) {
            $normalized = strtolower((string)$key);

            if ($normalized === strtolower(self::HEADER_PAYMENT) || $normalized === 'http_x_payment') {
                if (is_array($value)) {
                    if ($value === []) {
                        continue;
                    }

                    $first = reset($value);

                    return (string)$first;
                }

                if ($value === null) {
                    continue;
                }

                return (string)$value;
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

        if ($payload->x402Version !== self::X402_VERSION) {
            throw new PaymentRequiredException(
                'Unsupported x402 version',
                ErrorCodes::INVALID_VERSION
            );
        }

        // Basic validation
        if ($payload->scheme !== $requirements->scheme) {
            throw new PaymentRequiredException(
                "Payment scheme mismatch",
                ErrorCodes::INVALID_SCHEME
            );
        }

        if (!Validator::isSupportedScheme($requirements->scheme)) {
            throw new PaymentRequiredException(
                'Unsupported payment scheme: ' . $requirements->scheme,
                ErrorCodes::INVALID_SCHEME
            );
        }

        if (!Validator::isSupportedScheme($payload->scheme)) {
            throw new PaymentRequiredException(
                'Unsupported payment scheme: ' . $payload->scheme,
                ErrorCodes::INVALID_SCHEME
            );
        }

        if ($payload->network !== $requirements->network) {
            throw new PaymentRequiredException(
                "Payment network mismatch",
                ErrorCodes::INVALID_NETWORK
            );
        }

        if ($payload->scheme === 'exact') {
            if (Validator::isSvmNetwork($payload->network)) {
                $this->assertExactSvmAuthorizationMatchesRequirements($payload, $requirements);
            } else {
                $this->assertExactEvmAuthorizationMatchesRequirements($payload, $requirements);
            }
        }

        // Use facilitator for verification if available
        if ($this->facilitator !== null) {
            try {
                // Pass the original base64 encoded header to facilitator
                $verifyResponse = $this->facilitator->verify($paymentHeader, $requirements);
            } catch (FacilitatorException $e) {
                throw new PaymentRequiredException(
                    'Payment verification failed: ' . $e->getMessage(),
                    ErrorCodes::FACILITATOR_ERROR,
                    0,
                    $e
                );
            }

            if (!$verifyResponse->isValid) {
                throw new PaymentRequiredException(
                    "Payment verification failed: " . ($verifyResponse->invalidReason ?? 'Unknown reason'),
                    ErrorCodes::FACILITATOR_VERIFICATION_FAILED
                );
            }
        }

        return $payload;
    }

    /**
     * Settle payment.
     *
     * @param PaymentPayload|string $paymentPayload Payment payload or base64 encoded header
     * @param PaymentRequirements $requirements Payment requirements
     * @return array<string, mixed> Settlement response data
     * @throws ValidationException
     */
    public function settlePayment(PaymentPayload|string $paymentPayload, PaymentRequirements $requirements): array
    {
        if ($this->facilitator === null) {
            throw new ValidationException("Facilitator required for payment settlement");
        }

        // Keep the original header string for facilitator
        $paymentHeader = is_string($paymentPayload) ? $paymentPayload : Encoder::encodePaymentHeader($paymentPayload);

        try {
            $settleResponse = $this->facilitator->settle($paymentHeader, $requirements);
        } catch (FacilitatorException $e) {
            throw new ValidationException('Payment settlement failed: ' . $e->getMessage(), previous: $e);
        }

        if (!$settleResponse->success) {
            throw new ValidationException(
                "Payment settlement failed: " . ($settleResponse->errorReason ?? 'Unknown error')
            );
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
                $settlement = $this->settlePayment($payload, $requirements);
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

    /**
     * Ensure exact authorization matches requirements for EVM.
     */
    private function assertExactEvmAuthorizationMatchesRequirements(
        PaymentPayload $payload,
        PaymentRequirements $requirements
    ): void {
        if (!$payload->payload instanceof ExactPaymentPayload) {
            throw new PaymentRequiredException('Unsupported exact EVM payment payload');
        }

        $authorization = $payload->payload->authorization;

        // Check recipient address
        if (strtolower($authorization->to) !== strtolower($requirements->payTo)) {
            throw new PaymentRequiredException(
                'Payment recipient mismatch',
                ErrorCodes::INVALID_EVM_RECIPIENT
            );
        }

        // Check amount
        if ($this->compareUintStrings($authorization->value, $requirements->maxAmountRequired) !== 0) {
            throw new PaymentRequiredException(
                'Payment amount mismatch',
                ErrorCodes::INVALID_EVM_VALUE
            );
        }

        // Validate EIP-712 domain parameters in extra field
        if (!isset($requirements->extra['name']) || !is_string($requirements->extra['name'])) {
            throw new PaymentRequiredException(
                'EIP-712 domain name required in extra field',
                ErrorCodes::INVALID_EVM_SIGNATURE
            );
        }

        if (!isset($requirements->extra['version']) || !is_string($requirements->extra['version'])) {
            throw new PaymentRequiredException(
                'EIP-712 domain version required in extra field',
                ErrorCodes::INVALID_EVM_SIGNATURE
            );
        }

        // Check timestamp validity
        $now = time();

        // Verify authorization is not yet valid (validAfter is in the past)
        $validAfter = (int)$authorization->validAfter;
        if ($validAfter > $now) {
            throw new PaymentRequiredException(
                'Payment authorization not yet valid',
                ErrorCodes::INVALID_EVM_VALID_AFTER
            );
        }

        // Verify authorization is not expired (validBefore is in the future, with configurable buffer for block confirmations)
        $validBefore = (int)$authorization->validBefore;
        if ($validBefore < ($now + $this->validBeforeBufferSeconds)) {
            throw new PaymentRequiredException(
                'Payment authorization expired or expiring soon',
                ErrorCodes::INVALID_EVM_VALID_BEFORE
            );
        }

        // NOTE: Cryptographic signature verification (ECDSA recovery and validation)
        // is delegated to the facilitator. Local signature verification requires
        // additional dependencies for EIP-712 typed data hashing and secp256k1 recovery.
        // Ensure a facilitator is configured for production use to verify signatures.
    }

    /**
     * Ensure exact transaction matches requirements for Solana (SVM).
     */
    private function assertExactSvmAuthorizationMatchesRequirements(
        PaymentPayload $payload,
        PaymentRequirements $requirements
    ): void {
        if (!$payload->payload instanceof ExactSvmPayload) {
            throw new PaymentRequiredException('Unsupported exact SVM payment payload');
        }

        $transaction = $payload->payload->transaction;

        // Basic validation - transaction should be base64 encoded
        if (empty($transaction)) {
            throw new PaymentRequiredException(
                'Solana transaction is empty',
                ErrorCodes::INVALID_SVM_TRANSACTION
            );
        }

        // Decode to check it's valid base64
        $decoded = base64_decode($transaction, true);
        if ($decoded === false) {
            throw new PaymentRequiredException(
                'Invalid base64-encoded Solana transaction',
                ErrorCodes::INVALID_SVM_TRANSACTION
            );
        }

        // NOTE: Full Solana transaction parsing and validation is delegated to the facilitator.
        // Local validation would require parsing the serialized transaction to verify:
        // - SPL Token Transfer instruction is present
        // - Recipient ATA (Associated Token Account) matches payTo
        // - Transfer amount matches maxAmountRequired
        // - Token mint matches asset address
        // - Transaction signatures are valid
        // 
        // IMPORTANT: A facilitator is REQUIRED for Solana payments in production
        // to ensure proper transaction validation and signature verification.
        if ($this->facilitator === null) {
            throw new PaymentRequiredException(
                'Facilitator required for Solana transaction verification',
                ErrorCodes::FACILITATOR_ERROR
            );
        }
    }

    /**
     * Compare two unsigned integer strings.
     */
    private function compareUintStrings(string $a, string $b): int
    {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');

        $a = $a === '' ? '0' : $a;
        $b = $b === '' ? '0' : $b;

        $lenA = strlen($a);
        $lenB = strlen($b);

        if ($lenA === $lenB) {
            return strcmp($a, $b);
        }

        return $lenA <=> $lenB;
    }
}
