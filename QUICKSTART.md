# x402-php Quick Reference

## Installation

```bash
composer require mondb-dev/x402-php
```

## Basic Setup

```php
use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

// Required for production
$facilitator = new FacilitatorClient('https://facilitator.x402.org');
$handler = new PaymentHandler($facilitator, autoSettle: true);
```

## Create Payment Requirements

### EVM (Ethereum, Base, Optimism, etc.)

```php
$requirements = $handler->createPaymentRequirements(
    payTo: '0xYourAddress',
    amount: '1000000',                                          // 1 USDC (6 decimals)
    resource: 'https://api.example.com/data',
    description: 'Premium API access',
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',      // USDC on Base
    network: 'base-mainnet',
    extra: [
        'name' => 'USD Coin',                                   // ⚠️ REQUIRED
        'version' => '2'                                        // ⚠️ REQUIRED
    ]
);
```

### Solana

```php
$requirements = $handler->createPaymentRequirements(
    payTo: 'YourSolanaWalletAddress',
    amount: '1000000',                                          // 1 USDC (6 decimals)
    resource: 'https://api.example.com/data',
    description: 'Premium API access',
    asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',   // USDC on Solana
    network: 'solana-mainnet'
);
```

## Process Payment

```php
$result = $handler->processPayment($_SERVER, $requirements);

if ($result['verified']) {
    // ✅ Payment verified
    echo json_encode(['data' => 'Your content']);
    
    if ($result['settlement']) {
        header('X-Payment-Response: ' . 
            $handler->createPaymentResponseHeader($result['settlement']));
    }
} else {
    // ❌ Payment required
    $response = $handler->createPaymentRequiredResponse($requirements);
    $response->send();
}
```

## Supported Networks

### EVM Networks
- `ethereum-mainnet`, `ethereum-sepolia`, `ethereum-holesky`
- `base-mainnet`, `base-sepolia`
- `optimism-mainnet`, `optimism-sepolia`
- `arbitrum-mainnet`, `arbitrum-sepolia`
- `polygon-mainnet`, `polygon-amoy`

### SVM Networks
- `solana-mainnet`, `solana-devnet`, `solana-testnet`

## Common Token Addresses

### USDC on Base
- **Mainnet**: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`
- **Sepolia**: `0x036CbD53842c5426634e7929541eC2318f3dCF7e`

### USDC on Solana
- **Mainnet**: `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`

## Network-Specific Configuration

```php
// Base/Optimism/Arbitrum (~2s blocks)
$handler = new PaymentHandler($facilitator, true, validBeforeBufferSeconds: 6);

// Ethereum mainnet (~12s blocks)
$handler = new PaymentHandler($facilitator, true, validBeforeBufferSeconds: 36);

// Solana (~0.4s slots)
$handler = new PaymentHandler($facilitator, true, validBeforeBufferSeconds: 2);
```

## Error Handling

```php
use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Exceptions\FacilitatorException;

try {
    $result = $handler->processPayment($_SERVER, $requirements);
} catch (PaymentRequiredException $e) {
    // Payment verification failed
    $handler->createPaymentRequiredResponse($requirements)->send();
} catch (ValidationException $e) {
    // Invalid input
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (FacilitatorException $e) {
    // Facilitator error
    http_response_code(503);
    echo json_encode(['error' => 'Service temporarily unavailable']);
}
```

## Validation Utilities

```php
use X402\Validation\Validator;

// Validate addresses
Validator::isValidEthereumAddress('0x...');
Validator::isValidSolanaAddress('...');
Validator::isValidAddress($address, $network);

// Validate amounts
Validator::isValidUintString('1000000');

// Sanitize strings
$safe = Validator::sanitizeString($userInput);

// Check network type
Validator::isSvmNetwork('solana-mainnet');  // true
Validator::isEvmNetwork('base-mainnet');    // true
```

## Security Checklist

- [ ] ✅ Facilitator configured for production
- [ ] ✅ Using HTTPS for facilitator URL
- [ ] ✅ EIP-712 `extra` fields included for EVM payments
- [ ] ✅ Proper 402 response headers returned
- [ ] ✅ Error handling implemented
- [ ] ✅ Input validation on user data
- [ ] ✅ Rate limiting implemented (external)
- [ ] ✅ Payment logging implemented
- [ ] ✅ Environment variables for sensitive config

## Quick Troubleshooting

### "Payment recipient mismatch"
- Ensure `payTo` address matches exactly (case-insensitive for EVM)

### "EIP-712 domain name required"
- Add `extra: ['name' => '...', 'version' => '...']` to requirements

### "Facilitator required for Solana"
- Configure a FacilitatorClient for all Solana payments

### "Payment authorization expired"
- Increase `validBeforeBufferSeconds` for slower networks
- Check system clock synchronization

### "Invalid address"
- Verify address format for the network
- EVM: `0x` prefix + 40 hex chars
- Solana: 32-44 base58 chars

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/Middleware/PaymentHandlerTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Resources

- **Documentation**: [x402.gitbook.io/x402](https://x402.gitbook.io/x402)
- **Protocol Spec**: [github.com/coinbase/x402](https://github.com/coinbase/x402)
- **Security Guide**: See `SECURITY.md`
- **Examples**: See `examples/` directory

## Getting Help

- **GitHub Issues**: [github.com/mondb-dev/x402-php/issues](https://github.com/mondb-dev/x402-php/issues)
- **Security Issues**: Email maintainers privately (see SECURITY.md)
- **x402 Community**: Join x402 discussions

---

**Version**: 1.0.0  
**Last Updated**: October 28, 2025
