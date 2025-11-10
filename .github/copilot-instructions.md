# GitHub Copilot Instructions for x402-php

## Project Context

This is a PHP implementation of the x402 payments protocol - an HTTP-based payment standard for digital commerce.

## Critical Rules

### 1. Protocol Compliance
- **ONLY** implement features from the official x402 specification: https://github.com/coinbase/x402
- Current protocol version: **v1**
- Currently supported scheme: **`exact` ONLY**
- DO NOT suggest `range`, `upto`, or other payment schemes (they don't exist in the spec yet)

### 2. PHP Standards
```php
<?php

declare(strict_types=1); // ALWAYS include

// ALWAYS use type hints
public function processPayment(
    array $serverVars,
    PaymentRequirements $requirements
): array {
    // ...
}

// ALWAYS use readonly for immutable data
public function __construct(
    private readonly string $address,
    private readonly int $amount
) {}
```

### 3. Security Requirements

**Facilitator is MANDATORY:**
```php
// ✅ CORRECT
if (!$this->facilitator) {
    throw new ConfigurationException(
        'Facilitator required',
        ErrorCodes::FACILITATOR_REQUIRED
    );
}
```

**Always validate inputs:**
```php
// ✅ CORRECT
Validator::validateAddress($address, $network);
Validator::validateAmount($amount);
Validator::sanitizeString($userInput);
```

**Always use error codes:**
```php
// ✅ CORRECT
use X402\Exceptions\ErrorCodes;

throw new ValidationException(
    'Invalid address',
    ErrorCodes::INVALID_EVM_RECIPIENT
);

// ❌ WRONG
throw new ValidationException('Invalid address', 'invalid_address');
```

### 4. x402 HTTP 402 Quirk

**CRITICAL - PHP converts 402 to 401 if headers set in wrong order:**

```php
// ✅ CORRECT - Headers BEFORE status code
header('WWW-Authenticate: X-Payment');
header('X-Payment-Accept: ' . $acceptHeader);
http_response_code(402);

// ❌ WRONG - Results in HTTP 401 instead of 402!
http_response_code(402);
header('WWW-Authenticate: X-Payment');
```

### 5. EIP-712 Extra Field

**REQUIRED for exact scheme on EVM networks:**

```php
// ✅ CORRECT
$requirements = $handler->createPaymentRequirements(
    // ... other params ...
    extra: [
        'name' => 'USD Coin',    // REQUIRED
        'version' => '2'          // REQUIRED
    ]
);

// ❌ WRONG - Will fail signature verification
$requirements = $handler->createPaymentRequirements(
    // ... other params ...
    extra: null
);
```

### 6. Network Identifiers

Use official network IDs:
- `base-mainnet`, `base-sepolia`
- `ethereum-mainnet`, `ethereum-sepolia`  
- `solana-mainnet`, `solana-devnet`

NOT: `base`, `eth`, `sol`, `base-testnet`

### 7. Testing

```php
// Minimum 80% coverage
// Use Arrange-Act-Assert pattern
public function testFeature(): void
{
    // Arrange
    $input = 'test-data';
    
    // Act
    $result = $this->subject->method($input);
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

## Common Mistakes to Avoid

1. ❌ Inventing payment schemes not in the x402 spec
2. ❌ Skipping input validation
3. ❌ Using magic strings instead of ErrorCodes constants
4. ❌ Setting HTTP 402 status before headers
5. ❌ Omitting EIP-712 extra field for EVM networks
6. ❌ Missing strict type declarations
7. ❌ Exposing facilitator error details to clients
8. ❌ Using custom network IDs instead of official ones

## Documentation

- **Full Guidelines**: See [AI_GUIDELINES.md](../AI_GUIDELINES.md)
- **Contributing**: See [CONTRIBUTING.md](../CONTRIBUTING.md)
- **Security**: See [SECURITY.md](../SECURITY.md)
- **x402 Spec**: https://github.com/coinbase/x402

## Before Suggesting Code

Verify:
- [ ] Feature exists in x402 specification
- [ ] Strict types declared
- [ ] Type hints used
- [ ] Inputs validated
- [ ] Proper error codes
- [ ] PHPDoc comments
- [ ] Tests included
- [ ] Follows PSR-12
