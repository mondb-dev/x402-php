# x402-php

PHP implementation of the [x402 payments protocol](https://github.com/coinbase/x402) - a modern, internet-native payment standard for digital commerce.

## Features

- ✅ **Full x402 Protocol Support**: Implements the complete x402 specification
- ✅ **E-commerce & Banking Standards**: Built with proper validations and security best practices
- ✅ **Production Ready**: Comprehensive error handling and developer-friendly API
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

## Quick Start

### Using Coinbase Facilitator (Recommended)

```php
<?php

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

// Option 1: Use Coinbase Facilitator (easiest)
$facilitator = FacilitatorClient::coinbase(
    apiKey: getenv('COINBASE_FACILITATOR_API_KEY')  // Optional for testing
);

// Option 2: Use environment variables
$facilitator = FacilitatorClient::fromEnvironment();

// Option 3: Custom facilitator
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
    http_response_code(402);
    header('Content-Type: application/json');
    echo json_encode($handler->createPaymentRequiredResponse($requirements));
}
```

### Configuration

For production use with Coinbase Facilitator, configure your environment:

```bash
# Copy the example configuration
cp .env.example .env

# Edit .env and add your Coinbase Facilitator API key (optional for testing)
FACILITATOR_TYPE=coinbase
COINBASE_FACILITATOR_API_KEY=your_api_key_here
```

See `examples/coinbase-facilitator.php` for a complete working example.

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

## Security

This library implements several security measures:

1. **Input Validation**: All inputs are validated before processing
2. **Type Safety**: Uses PHP 8.1+ strict types
3. **Sanitization**: All user inputs are sanitized to prevent XSS
4. **Address Validation**: Ethereum addresses are validated with regex
5. **Amount Validation**: Amounts are validated as proper uint256 strings

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

1. **Always use HTTPS** for facilitator communication
2. **Validate all inputs** before creating payment requirements
3. **Log payment attempts** for audit trails
4. **Implement rate limiting** to prevent abuse
5. **Monitor for replay attacks** (track used nonces)
6. **Use environment variables** for sensitive configuration
7. **Keep dependencies updated** to patch security vulnerabilities

### Reporting Security Issues

Please report security vulnerabilities to the maintainers privately.

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

- [ ] Support for additional payment schemes
- [ ] Built-in middleware for popular PHP frameworks (Laravel, Symfony)
- [ ] WebSocket support for real-time payment notifications
- [ ] Additional network support (Ethereum, Polygon, etc.)
- [ ] Rate limiting utilities
- [ ] Payment analytics and reporting

## Support

For issues and questions:
- GitHub Issues: [github.com/mondb-dev/x402-php/issues](https://github.com/mondb-dev/x402-php/issues)
- x402 Community: Join the x402 discussions

---

Made with ❤️ for the future of internet payments
