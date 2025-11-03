# x402-php

PHP implementation of the [x402 payments protocol](https://github.com/coinbase/x402) - a modern, internet-native payment standard for digital commerce.

## Features

- ✅ **Full x402 Protocol Support**: Implements the complete x402 specification
- ✅ **Enterprise Security**: Nonce tracking, rate limiting, compliance checks, audit logging
- ✅ **Production Ready**: Comprehensive error handling and developer-friendly API
- ✅ **Default Facilitator**: Pre-configured with PayAI facilitator (https://facilitator.payai.network)
- ✅ **Replay Attack Prevention**: Built-in nonce tracking with Redis backend
- ✅ **DoS Protection**: Configurable rate limiting to prevent abuse
- ✅ **Compliance Ready**: Optional AML/KYC integration hooks
- ✅ **Monitoring**: PSR-3 logging and metrics interfaces for observability
- ✅ **Composer Compatible**: Easy installation via Composer
- ✅ **Type Safe**: Uses PHP 8.1+ strict types for reliability
- ✅ **Well Tested**: Comprehensive PHPUnit test coverage
- ✅ **PSR-4 Compliant**: Follows modern PHP standards

## What is x402?

x402 is an open standard for HTTP-based payments that enables:
- **Low friction payments**: No credit card forms, instant settlement
- **Micropayments**: Support for payments as low as $0.001
- **No fees**: Zero platform fees, just blockchain gas costs
- **Fast settlement**: ~2 second settlement time
- **Gasless**: Resource servers and clients don't need to manage gas

## Installation

```bash
composer require mondb-dev/x402-php
```

## Requirements

- PHP 8.1 or higher
- JSON extension
- Guzzle HTTP client
- Redis extension (optional, but recommended for production)
- PSR-3 Logger implementation (optional, recommended)

## Quick Start

### Basic Setup

```php
<?php

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

// Option 1: Use PayAI Facilitator (default, recommended)
$facilitator = FacilitatorClient::payai(
    apiKey: getenv('FACILITATOR_API_KEY')  // Optional for testing
);

// Option 2: Use Coinbase Facilitator
$facilitator = FacilitatorClient::coinbase(
    apiKey: getenv('COINBASE_FACILITATOR_API_KEY')
);

// Option 3: Use environment variables
$facilitator = FacilitatorClient::fromEnvironment();

// Option 4: Custom facilitator
$facilitator = new FacilitatorClient('https://your-facilitator.example.com');

// Initialize payment handler
$handler = new PaymentHandler($facilitator, autoSettle: true);

// Define what you're selling
$requirements = $handler->createPaymentRequirements(
    payTo: '0xYourAddress',
    amount: '1000000', // 1 USDC (6 decimals)
    resource: 'https://api.example.com/data',
    description: 'Premium API access',
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', // USDC on Base
    network: 'base-mainnet',
    extra: [
        'name' => 'USD Coin',      // Required for EIP-712 signature verification
        'version' => '2'            // Required for EIP-712 signature verification
    ],
    id: 'payment-' . uniqid()  // Optional but recommended for Coinbase
// Process incoming request
$result = $handler->processPayment($_SERVER, $requirements);

if ($result['verified']) {
    // Payment successful - serve content
    echo json_encode(['data' => 'Your premium content']);
    
    // Include settlement info in response header
    if ($result['settlement']) {
        header('X-Payment-Response: ' . 
            $handler->createPaymentResponseHeader($result['settlement']));
    }
} else {
    // Payment required
    $paymentRequiredResponse = $handler->createPaymentRequiredResponse($requirements);
    $paymentRequiredResponse->send();
}
```

### Production Setup with All Security Features

For production deployments, enable all security features:

```php
use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Nonce\RedisNonceTracker;
use X402\RateLimit\RedisRateLimiter;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// Configure Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Configure facilitator
$facilitator = FacilitatorClient::payai(apiKey: getenv('FACILITATOR_API_KEY'));

// Configure nonce tracker (prevents replay attacks)
$nonceTracker = new RedisNonceTracker($redis, 'myapp');

// Configure rate limiter (prevents DoS)
$rateLimiter = new RedisRateLimiter($redis, maxAttempts: 10, decaySeconds: 60);

// Configure logger
$logger = new Logger('x402');
$logger->pushHandler(new RotatingFileHandler('/var/log/x402/payments.log', 30));

// Create secure payment handler
$handler = new PaymentHandler(
    facilitator: $facilitator,
    autoSettle: true,
    validBeforeBufferSeconds: 6,
    nonceTracker: $nonceTracker,
    rateLimiter: $rateLimiter,
    logger: $logger
);
```

**Required Environment Variables**:
```bash
# Facilitator (REQUIRED)
FACILITATOR_BASE_URL=https://facilitator.payai.network
FACILITATOR_API_KEY=your_api_key_here
APP_ENV=production

# Redis (REQUIRED for nonce tracking)
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_password

# Rate Limiting (RECOMMENDED)
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=10
RATE_LIMIT_DECAY_SECONDS=60
```

See `examples/production-setup.php` for a complete working example with all security features.

### Validate Production Readiness

Before deploying, run the production validator:

```bash
php bin/validate-production.php
```

This checks:
- ✅ Facilitator configuration
- ✅ Redis connectivity
- ✅ Nonce tracking setup
- ✅ Rate limiting configuration
- ✅ PHP extensions
- ✅ Security settings

See [SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md) for complete production deployment guidelines.

## Architecture

The library consists of several key components:

### Core Types
- `PaymentRequirements`: Defines what payment is required for a resource
- `PaymentPayload`: Contains the payment information from the client
- `PaymentRequiredResponse`: 402 response sent to clients
- `VerifyResponse`: Result of payment verification
- `SettleResponse`: Result of payment settlement

### Validation & Security
- **Input Validation**: Validates all payment data structures
- **Address Validation**: Ensures Ethereum addresses are properly formatted
- **Amount Validation**: Validates amounts are valid uint256 strings
- **Network Validation**: Validates network IDs against supported networks
- **Timestamp Validation**: Validates payment authorization time windows
- **Signature Validation**: Validates EIP-712 signature format (65 bytes)
- **Nonce Validation**: Validates nonce format (32 bytes)
- **XSS Prevention**: Sanitizes string inputs to prevent injection attacks
- **URL Validation**: Validates and sanitizes URLs

### Facilitator Client
The `FacilitatorClient` handles communication with x402 facilitator servers:
- Payment verification
- Payment settlement
- Querying supported schemes and networks

### Payment Handler
The `PaymentHandler` provides high-level middleware functionality:
- Creating payment requirements
- Processing payment headers
- Verifying payments
- Settling payments
- Managing payment flow

### Important: EIP-712 Extra Field

For the `exact` scheme on EVM networks (Base, Ethereum), the `extra` field **must** contain the ERC-20 token's `name` and `version` fields. These are required for EIP-712 signature verification:

```php
$requirements = $handler->createPaymentRequirements(
    // ... other parameters ...
    extra: [
        'name' => 'USD Coin',      // Token name from ERC-20 contract
        'version' => '2'            // Token version from ERC-20 contract
    ]
);
```

Common values for USDC:
- **Base/Ethereum USDC**: `name: "USD Coin"`, `version: "2"`

Omitting the `extra` field or missing `name`/`version` will cause signature verification to fail.

## Advanced Usage

### Checking Facilitator Support

```php
// Get supported configuration from facilitator
$config = $facilitator->getSupported();

// Check if specific network is supported
if ($config->supportsNetwork('base-mainnet')) {
    $network = $config->getNetwork('base-mainnet');
    echo "Chain ID: {$network->chainId}\n";
    echo "Explorer: {$network->explorerUrl}\n";
}

// Check if specific payment scheme is supported
if ($config->supportsScheme('exact')) {
    echo "Facilitator supports exact payment scheme\n";
}
```

### Custom Validation

```php
use X402\Validation\Validator;
use X402\Exceptions\ValidationException;

// Validate Ethereum address
if (!Validator::isValidEthereumAddress($address)) {
    throw new ValidationException('Invalid address');
}

// Validate amount format
if (!Validator::isValidUintString($amount)) {
    throw new ValidationException('Invalid amount');
}

// Sanitize user input
$safe = Validator::sanitizeString($userInput);
```

### Manual Payment Verification

```php
// Extract payment header
$paymentHeader = $handler->extractPaymentHeader($_SERVER);

if ($paymentHeader !== null) {
    try {
        // Verify payment
        $payload = $handler->verifyPayment($paymentHeader, $requirements);
        
        // Manually settle if needed
        $settlement = $handler->settlePayment($payload, $requirements);

        echo "Transaction: " . $settlement['transaction'];
    } catch (PaymentRequiredException $e) {
        echo "Payment verification failed: " . $e->getMessage();
    }
}
```

### Working with Different Networks

```php
// Base Mainnet
$requirements = $handler->createPaymentRequirements(
    payTo: $yourAddress,
    amount: '1000000',
    resource: $resourceUrl,
    description: $description,
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', // USDC
    network: 'base-mainnet',
    extra: [
        'name' => 'USD Coin',
        'version' => '2'
    ]
);

// Base Sepolia (Testnet)
$requirements = $handler->createPaymentRequirements(
    payTo: $yourAddress,
    amount: '1000000',
    resource: $resourceUrl,
    description: $description,
    asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e', // USDC
    network: 'base-sepolia',
    extra: [
        'name' => 'USD Coin',
        'version' => '2'
    ]
);
```

## Common Pitfalls

### ⚠️ HTTP 402 vs 401 Status Code Issue

**Problem**: PHP automatically changes HTTP 402 to 401 when `WWW-Authenticate` header is set after the status code.

**Solution**: Always use the `send()` method or set headers before status code:

```php
// ❌ WRONG - Returns 401 instead of 402
http_response_code(402);
header('WWW-Authenticate: X-Payment');
echo json_encode($response);

// ✅ CORRECT - Returns 402
header('WWW-Authenticate: X-Payment');
http_response_code(402);
echo json_encode($response);

// ✅ BEST - Use send() method
$paymentRequired->send();  // Handles this automatically
```

**Why it matters**: Clients expect HTTP 402 for payment requirements. If they receive 401, they'll treat it as an authentication error, breaking the x402 protocol.

**Test your responses**:
```bash
curl -I https://your-api.com/endpoint
# Should return: HTTP/1.1 402 Payment Required
# NOT: HTTP/1.1 401 Unauthorized
```

## Testing

Run the test suite:

```bash
composer install
./vendor/bin/phpunit
```

## Code Quality

Run PHPStan for static analysis:

```bash
./vendor/bin/phpstan analyse src --level=8
```

Run PHP CS Fixer for code style:

```bash
./vendor/bin/php-cs-fixer fix
```

## Examples

See the `examples/` directory for complete working examples:
- `basic-usage.php`: Simple payment-required endpoint
- `production-setup.php`: Production-ready setup with all security features
- `coinbase-facilitator.php`: Using Coinbase facilitator
- `solana-usage.php`: Solana (SVM) payment example

## Security

This library implements comprehensive enterprise-grade security measures:

### Built-in Security Features

1. **Input Validation**: All inputs are validated before processing
2. **Type Safety**: Uses PHP 8.1+ strict types
3. **Sanitization**: All user inputs are sanitized to prevent XSS
4. **Address Validation**: Ethereum and Solana addresses validated with regex
5. **Amount Validation**: Amounts validated as proper uint256 strings (with overflow protection)
6. **URL Validation**: Only http/https schemes allowed
7. **EIP-712 Domain Validation**: Ensures proper name/version format
8. **Timing Buffer Validation**: Prevents negative or excessive buffer values

### Advanced Security (Production)

9. **Replay Attack Prevention**: Redis-based nonce tracking prevents payment reuse
10. **Rate Limiting**: Sliding window rate limiter prevents DoS and brute force attacks
11. **Compliance Checks**: Optional AML/KYC integration for sanctioned addresses
12. **Audit Logging**: PSR-3 logger integration for complete audit trails
13. **Error Sanitization**: Facilitator errors sanitized to prevent information leakage
14. **Production Enforcement**: Automatically requires facilitator when APP_ENV=production
15. **Metrics & Monitoring**: Built-in metrics interfaces for observability

### ⚠️ Important Security Considerations

#### Cryptographic Signature Verification

**This library delegates cryptographic signature verification to the facilitator server.** 

For EVM (Ethereum/Base) payments:
- The library validates signature **format** (65-byte hex string)
- **Actual ECDSA recovery and verification is performed by the facilitator**
- Local verification would require additional cryptographic libraries for EIP-712 typed data hashing and secp256k1 signature recovery

**For production use:**
- ✅ **A facilitator is REQUIRED** to ensure proper signature verification
- ✅ Use trusted facilitator services (e.g., `https://facilitator.x402.org`)
- ⚠️ Without a facilitator, payments are NOT cryptographically verified
- ⚠️ Never accept payments in production without facilitator verification

#### Solana Transaction Verification

**This library requires a facilitator for Solana (SVM) transaction validation.**

For Solana payments:
- The library validates transaction is **base64-encoded**
- **Full transaction parsing and validation is performed by the facilitator**
- Local validation would require Solana transaction deserializer and instruction parser

**The facilitator verifies:**
- SPL Token Transfer instruction is present and valid
- Recipient ATA (Associated Token Account) matches `payTo`
- Transfer amount matches `maxAmountRequired`
- Token mint matches `asset` address
- Transaction signatures are cryptographically valid

**For production use:**
- ✅ **A facilitator is REQUIRED** for all Solana payments
- ⚠️ The library will reject Solana payments without a facilitator configured
- ⚠️ Never process Solana transactions without facilitator verification

#### Configuration for Production

```php
// ✅ SECURE: Use facilitator for production
$facilitator = new FacilitatorClient('https://facilitator.x402.org');
$handler = new PaymentHandler($facilitator, autoSettle: true);

// ❌ INSECURE: Do not use without facilitator in production
$handler = new PaymentHandler(); // Missing facilitator!
```

#### Network-Specific Timing Buffers

Different blockchain networks have different block times. Configure timing buffers appropriately:

```php
// EVM L2 (Base, Optimism, Arbitrum): ~2s blocks
$handler = new PaymentHandler($facilitator, autoSettle: true, validBeforeBufferSeconds: 6);

// Ethereum mainnet: ~12s blocks
$handler = new PaymentHandler($facilitator, autoSettle: true, validBeforeBufferSeconds: 36);

// Solana: ~0.4s slots
$handler = new PaymentHandler($facilitator, autoSettle: true, validBeforeBufferSeconds: 2);
```

#### Additional Security Best Practices

1. **Always use HTTPS** for facilitator communication (enforced)
2. **Validate all inputs** before creating payment requirements
3. **Log payment attempts** for audit trails (use PSR-3 logger)
4. **Implement rate limiting** to prevent abuse (RedisRateLimiter included)
5. **Monitor for replay attacks** (use RedisNonceTracker)
6. **Use environment variables** for sensitive configuration
7. **Keep dependencies updated** to patch security vulnerabilities
8. **Run production validator** before deploying (`bin/validate-production.php`)
9. **Review SECURITY_CHECKLIST.md** for complete deployment guidelines
10. **Enable metrics** for real-time threat detection

### Security Checklist

Before deploying to production, review [SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md) which covers:

- ✅ **Mandatory**: Facilitator configuration
- ✅ **Mandatory**: Nonce tracking (replay prevention)
- ⚠️ **Highly Recommended**: Rate limiting
- ⚠️ **Highly Recommended**: Audit logging
- ⚪ **Optional**: Compliance checks (AML/KYC)
- ⚪ **Optional**: Metrics and monitoring

### Reporting Security Issues

Please report security vulnerabilities according to [SECURITY.md](SECURITY.md).

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Follow PSR-12 coding standards
2. Add tests for new features
3. Update documentation as needed
4. Run the test suite before submitting PRs

## License

Apache-2.0 License - see LICENSE file for details

## Resources

- [x402 Protocol Specification](https://github.com/coinbase/x402)
- [x402 Documentation](https://x402.gitbook.io/x402)
- [Base Network](https://base.org)

## Roadmap

- [x] Replay attack prevention (nonce tracking)
- [x] Rate limiting utilities
- [x] Compliance check interfaces (AML/KYC)
- [x] Metrics and monitoring interfaces
- [x] PSR-3 logging support
- [x] Production readiness validator
- [ ] Support for additional payment schemes
- [ ] Built-in middleware for popular PHP frameworks (Laravel, Symfony)
- [ ] WebSocket support for real-time payment notifications
- [ ] Payment analytics and reporting dashboard

## Support

For issues and questions:
- GitHub Issues: [github.com/mondb-dev/x402-php/issues](https://github.com/mondb-dev/x402-php/issues)
- x402 Community: Join the x402 discussions

---

Made with ❤️ for the future of internet payments
