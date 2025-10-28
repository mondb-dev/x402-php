# Security Policy

## Overview

This document outlines the security considerations, limitations, and best practices for using x402-php in production environments.

## Critical Security Requirements

### 🔐 Facilitator is REQUIRED for Production

**This library REQUIRES a facilitator service for cryptographic verification in production environments.**

#### Why a Facilitator is Required

1. **EVM Signature Verification**: The library validates signature format but does NOT perform cryptographic ECDSA signature recovery and verification. This is delegated to the facilitator.

2. **Solana Transaction Validation**: The library does NOT parse or validate Solana transactions locally. Complete transaction validation including instruction parsing, amount verification, and signature checking is performed by the facilitator.

#### Without a Facilitator

- ⚠️ EVM payments are NOT cryptographically verified
- ⚠️ Solana payments will be REJECTED (facilitator required by design)
- ⚠️ Replay attacks are NOT prevented
- ⚠️ Payment amounts are NOT verified on Solana

#### Proper Configuration

```php
// ✅ CORRECT: Production configuration with facilitator
$facilitator = new FacilitatorClient('https://facilitator.x402.org');
$handler = new PaymentHandler($facilitator, autoSettle: true);

// ❌ INCORRECT: Never use in production without facilitator
$handler = new PaymentHandler(); // Missing facilitator!
```

## What This Library DOES Validate

### ✅ Local Validation (Without Facilitator)

#### EVM Payments
- Signature format (65-byte hex string)
- Recipient address matches requirements
- Amount matches requirements
- Timestamp validity (validAfter < now < validBefore)
- EIP-712 domain parameters present (name, version)

#### Solana Payments
- Transaction is base64-encoded
- Transaction is not empty
- **Note**: Facilitator is REQUIRED - library will reject without one

### ✅ With Facilitator

All local validations PLUS:

#### EVM Payments
- Cryptographic signature verification (ECDSA recovery)
- Signature matches the `from` address
- Nonce uniqueness (replay prevention)

#### Solana Payments
- Transaction deserialization and parsing
- SPL Token Transfer instruction validation
- Recipient ATA (Associated Token Account) matches
- Transfer amount matches requirements
- Token mint matches asset
- Transaction signatures are valid

## Network-Specific Considerations

### Timing Buffers

Different networks have different block times. Configure appropriately:

```php
// EVM L2 (Base, Optimism, Arbitrum): ~2s blocks
// Default 6s buffer = 3 blocks
$handler = new PaymentHandler($facilitator, autoSettle: true, validBeforeBufferSeconds: 6);

// Ethereum mainnet: ~12s blocks
// 36s buffer = 3 blocks
$handler = new PaymentHandler($facilitator, autoSettle: true, validBeforeBufferSeconds: 36);

// Solana: ~0.4s slots
// 2s buffer = 5 slots
$handler = new PaymentHandler($facilitator, autoSettle: true, validBeforeBufferSeconds: 2);
```

### Supported Networks

The library currently supports:

#### EVM Networks
- Ethereum (mainnet, sepolia, holesky)
- Base (mainnet, sepolia)
- Optimism (mainnet, sepolia)
- Arbitrum (mainnet, sepolia)
- Polygon (mainnet, amoy)

#### SVM Networks
- Solana (mainnet, devnet, testnet)

## Required HTTP Headers

Per x402 specification, 402 responses MUST include:

```php
$paymentRequiredResponse = $handler->createPaymentRequiredResponse($requirements);

// Get all required headers
$headers = $paymentRequiredResponse->getHeaders();
foreach ($headers as $name => $value) {
    header("{$name}: {$value}");
}

// Returns:
// - WWW-Authenticate: X-Payment
// - Content-Type: application/json
// - X-Payment-Accept: exact (or comma-separated list of schemes)
```

## EIP-712 Domain Parameters

For EVM payments, the `extra` field MUST contain EIP-712 domain parameters:

```php
$requirements = $handler->createPaymentRequirements(
    // ... other parameters ...
    extra: [
        'name' => 'USD Coin',      // Token name from ERC-20 contract
        'version' => '2'            // Token version from ERC-20 contract
    ]
);
```

Missing these parameters will cause validation to fail.

## Best Practices

### 1. Always Use HTTPS

```php
// ✅ CORRECT
$facilitator = new FacilitatorClient('https://facilitator.x402.org');

// ❌ INCORRECT
$facilitator = new FacilitatorClient('http://facilitator.x402.org'); // Will throw exception
```

### 2. Use Environment Variables

```php
$facilitatorUrl = getenv('X402_FACILITATOR_URL') ?: 'https://facilitator.x402.org';
$facilitatorApiKey = getenv('X402_FACILITATOR_API_KEY');

$facilitator = new FacilitatorClient($facilitatorUrl, 60, $facilitatorApiKey);
```

### 3. Implement Rate Limiting

```php
// Example using a simple rate limiter
$rateLimiter = new RateLimiter();
if (!$rateLimiter->allow($_SERVER['REMOTE_ADDR'], 10, 60)) {
    http_response_code(429);
    exit('Too many requests');
}
```

### 4. Log All Payment Attempts

```php
$logger = new Logger();
$result = $handler->processPayment($headers, $requirements);

$logger->log('payment_attempt', [
    'verified' => $result['verified'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'timestamp' => time(),
    'amount' => $requirements->maxAmountRequired,
    'network' => $requirements->network,
]);
```

### 5. Monitor for Replay Attacks

Track nonces to prevent replay attacks:

```php
// Pseudo-code
if ($result['verified']) {
    $nonce = $result['payload']->payload->authorization->nonce;
    
    if ($nonceStore->exists($nonce)) {
        throw new PaymentRequiredException('Nonce already used');
    }
    
    $nonceStore->store($nonce, time() + 3600); // Store for 1 hour
}
```

### 6. Validate All Inputs

```php
use X402\Validation\Validator;
use X402\Exceptions\ValidationException;

// Validate addresses before creating requirements
if (!Validator::isValidAddress($payTo, $network)) {
    throw new ValidationException('Invalid payTo address');
}

if (!Validator::isValidAddress($asset, $network)) {
    throw new ValidationException('Invalid asset address');
}

// Sanitize user inputs
$description = Validator::sanitizeString($userInput);
```

### 7. Handle Errors Gracefully

```php
try {
    $result = $handler->processPayment($headers, $requirements);
} catch (PaymentRequiredException $e) {
    $logger->error('Payment verification failed', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);
    
    // Return appropriate error response
    http_response_code(402);
    // ... return payment required response
} catch (ValidationException $e) {
    $logger->error('Validation error', ['error' => $e->getMessage()]);
    http_response_code(400);
    // ... return bad request
} catch (FacilitatorException $e) {
    $logger->error('Facilitator error', ['error' => $e->getMessage()]);
    http_response_code(503);
    // ... return service unavailable
}
```

## Known Limitations

### 1. No Local Signature Verification
- EVM signatures are not verified cryptographically without facilitator
- Requires trust in facilitator service

### 2. No Solana Transaction Parsing
- Solana transactions are not parsed locally
- All Solana validation requires facilitator

### 3. No Built-in Replay Prevention
- Nonce tracking must be implemented separately
- No built-in database for tracking used nonces

### 4. No Built-in Rate Limiting
- Rate limiting must be implemented separately
- Consider using middleware or reverse proxy

### 5. Single Scheme Support
- Currently only supports "exact" scheme
- "range" and other schemes not yet implemented

## Reporting Security Issues

If you discover a security vulnerability, please email the maintainers privately at:

**security@example.com** (replace with actual email)

**Do NOT open a public issue for security vulnerabilities.**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if applicable)

We aim to respond within 48 hours.

## Security Updates

Stay informed about security updates:
- Watch the GitHub repository for releases
- Subscribe to security advisories
- Follow the changelog for security patches

## Compliance Considerations

### PCI DSS
This library does NOT handle credit card data and is NOT subject to PCI DSS.

### GDPR
- Payment addresses are public blockchain data
- No personal data is processed by the library
- Implement your own privacy policy for payment logs

### AML/KYC
- This library does NOT perform AML/KYC checks
- Resource servers are responsible for compliance with local regulations

## License

Apache-2.0 License - see LICENSE file for details

---

**Last Updated**: October 28, 2025
