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

### Basic Server Implementation

```php
<?php

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

// Initialize payment handler
$facilitator = new FacilitatorClient('https://facilitator.x402.org');
$handler = new PaymentHandler($facilitator, autoSettle: true);

// Define what you're selling
$requirements = $handler->createPaymentRequirements(
    payTo: '0xYourAddress',
    amount: '1000000', // 1 USDC (6 decimals)
    resource: 'https://api.example.com/data',
    description: 'Premium API access',
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', // USDC on Base
    network: 'base-mainnet'
);

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

## Advanced Usage

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
        $settlement = $handler->settlePayment($paymentHeader, $requirements);
        
        echo "Transaction: " . $settlement['txHash'];
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
    network: 'base-mainnet'
);

// Base Sepolia (Testnet)
$requirements = $handler->createPaymentRequirements(
    payTo: $yourAddress,
    amount: '1000000',
    resource: $resourceUrl,
    description: $description,
    asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e', // USDC
    network: 'base-sepolia'
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
