# AI Development Assistant Guidelines

This document provides critical guidelines for AI assistants (like GitHub Copilot, Cursor, Claude, GPT, etc.) working on the x402-php codebase. Following these guidelines ensures strict protocol compliance and maintains code quality.

---

## 🚨 CRITICAL: x402 Protocol Compliance

### Protocol Authority

**ALWAYS refer to the official x402 specification:**
- **Primary Source**: https://github.com/coinbase/x402
- **Official Docs**: https://docs.cdp.coinbase.com/x402/welcome
- **Specification**: `specs/` directory in the x402 repository

**DO NOT** invent features or deviate from the specification.

### Current Protocol Version

- **Protocol Version**: v1
- **Supported Scheme**: `exact` only
- **Future Schemes**: Not yet specified in the protocol

---

## 📋 Mandatory Protocol Requirements

### 1. Payment Scheme: `exact` Only

```php
// ✅ CORRECT - Only scheme currently in x402 spec
$requirements = $handler->createPaymentRequirements(
    scheme: 'exact',  // Only valid value
    amount: '1000000',
    // ...
);

// ❌ WRONG - These schemes don't exist in x402 v1
$requirements = $handler->createPaymentRequirements(
    scheme: 'range',   // NOT in spec
    minAmount: '500',  // NOT in spec
    maxAmount: '1000', // NOT in spec
);
```

**Why**: The x402 protocol currently only specifies the `exact` payment scheme. Any other schemes (`range`, `upto`, `subscription`, etc.) are theoretical examples, not part of the official specification.

### 2. HTTP 402 Status Code

```php
// ✅ CORRECT - Set header BEFORE status code
header('WWW-Authenticate: X-Payment');
header('X-Payment-Accept: ' . $acceptHeader);
http_response_code(402);

// ❌ WRONG - PHP converts 402 to 401 if header set after
http_response_code(402);
header('WWW-Authenticate: X-Payment');  // This causes 401!
```

**Why**: PHP has a quirk where setting `WWW-Authenticate` after `http_response_code(402)` causes the status to change to 401. This breaks x402 protocol compliance.

### 3. Required Headers

**Payment Required Response (402):**
```php
// MUST include both headers
'WWW-Authenticate: X-Payment'
'X-Payment-Accept: <base64-encoded-json>'
```

**Payment Request (Client):**
```php
// MUST include
'X-Payment: <base64-encoded-json>'
```

**Payment Response (200 with settlement):**
```php
// MUST include after successful payment
'X-Payment-Response: <base64-encoded-json>'
```

### 4. EIP-712 Domain Parameters (EVM Networks)

```php
// ✅ CORRECT - REQUIRED for exact scheme on EVM
$requirements = $handler->createPaymentRequirements(
    // ... other params ...
    extra: [
        'name' => 'USD Coin',      // REQUIRED
        'version' => '2'            // REQUIRED
    ]
);

// ❌ WRONG - Missing extra field
$requirements = $handler->createPaymentRequirements(
    // ... other params ...
    extra: null  // Will fail signature verification!
);
```

**Why**: EIP-712 signature verification requires the ERC-20 token's `name` and `version` for domain separation. Without these, signature verification will fail.

### 5. Payment Payload Structure

**MUST include exactly these fields:**
```typescript
{
  x402Version: number;     // Currently: 1
  scheme: string;          // Currently: "exact"
  network: string;         // e.g., "base-mainnet"
  payload: <scheme-dependent>
}
```

**For `exact` scheme on EVM:**
```typescript
{
  x402Version: 1,
  scheme: "exact",
  network: "base-mainnet",
  payload: {
    authorization: {
      v: string,
      r: string,
      s: string,
      validAfter: string,
      validBefore: string,
      nonce: string,
      value: string
    }
  }
}
```

**For `exact` scheme on Solana:**
```typescript
{
  x402Version: 1,
  scheme: "exact",
  network: "solana-mainnet",
  payload: {
    transaction: string  // base64-encoded signed transaction
  }
}
```

---

## 🔒 Security Requirements

### 1. Facilitator Requirement

```php
// ✅ CORRECT - Always require facilitator in production
if (getenv('APP_ENV') === 'production' && !$this->facilitator) {
    throw new ConfigurationException(
        'Facilitator is required in production',
        ErrorCodes::FACILITATOR_REQUIRED
    );
}

// ❌ WRONG - Never allow production without facilitator
if ($this->facilitator) {
    $this->facilitator->verify($payload);
}
// Missing else clause - payment not verified!
```

**Why**: This library delegates cryptographic verification to the facilitator. Without it, payments are NOT verified.

### 2. Nonce Tracking (Replay Attack Prevention)

```php
// ✅ CORRECT - Always check nonce before verification
if ($this->nonceTracker && $this->nonceTracker->isUsed($nonce)) {
    throw new ValidationException(
        'Nonce already used',
        ErrorCodes::NONCE_ALREADY_USED
    );
}

// Mark as used AFTER successful verification
$this->nonceTracker->markUsed($nonce);
```

**Why**: Without nonce tracking, attackers can replay valid payments multiple times.

### 3. Input Validation

**ALWAYS validate before processing:**

```php
// ✅ CORRECT - Validate everything
Validator::validateAddress($address, $network);
Validator::validateAmount($amount);
Validator::validateNetwork($network);
Validator::validateTimestamp($validAfter, $validBefore, $buffer);
Validator::sanitizeString($userInput);

// ❌ WRONG - Never trust input
$this->processPayment($_POST['payment']);  // NO!
```

### 4. Rate Limiting

```php
// ✅ CORRECT - Rate limit verification attempts
if ($this->rateLimiter && !$this->rateLimiter->attempt($identifier)) {
    throw new ValidationException(
        'Rate limit exceeded',
        ErrorCodes::RATE_LIMIT_EXCEEDED
    );
}
```

---

## 💻 Code Quality Standards

### 1. Strict Types

**ALWAYS declare strict types:**

```php
<?php

declare(strict_types=1);

namespace X402\YourNamespace;
```

### 2. Type Hints

**ALWAYS use type hints:**

```php
// ✅ CORRECT
public function processPayment(
    array $serverVars,
    PaymentRequirements $requirements
): array {
    // ...
}

// ❌ WRONG - No type hints
public function processPayment($serverVars, $requirements) {
    // ...
}
```

### 3. Readonly Properties

**Use readonly for immutable data:**

```php
// ✅ CORRECT
public function __construct(
    private readonly string $address,
    private readonly string $amount,
    private readonly string $network
) {}

// ❌ WRONG - Allows mutation
public function __construct(
    private string $address,
    private string $amount,
    private string $network
) {}
```

### 4. Error Codes

**ALWAYS use standardized error codes:**

```php
// ✅ CORRECT
use X402\Exceptions\ErrorCodes;

throw new ValidationException(
    'Invalid address',
    ErrorCodes::INVALID_EVM_RECIPIENT
);

// ❌ WRONG - Magic strings
throw new ValidationException('Invalid address', 'invalid_address');
```

**Available Error Codes** (see `src/Exceptions/ErrorCodes.php`):
- General: `INVALID_VERSION`, `INVALID_SCHEME`, `INVALID_NETWORK`
- EVM: `INVALID_EVM_SIGNATURE`, `INVALID_EVM_RECIPIENT`, `INVALID_EVM_VALUE`
- SVM: `INVALID_SVM_TRANSACTION`, `INVALID_SVM_AMOUNT_MISMATCH`
- Security: `NONCE_ALREADY_USED`, `RATE_LIMIT_EXCEEDED`, `COMPLIANCE_FAILED`
- Facilitator: `FACILITATOR_ERROR`, `FACILITATOR_VERIFICATION_FAILED`

### 5. PHPDoc Comments

**Document all public methods:**

```php
/**
 * Validates an Ethereum address format.
 *
 * @param string $address The address to validate (must include 0x prefix)
 * @param string $network The network identifier
 * @return bool True if valid, false otherwise
 * @throws ValidationException If address format is invalid
 */
public function validateAddress(string $address, string $network): bool
{
    // ...
}
```

---

## 🧪 Testing Requirements

### 1. Test Coverage

**Minimum 80% coverage for new code:**

```php
// ✅ CORRECT - Test all paths
public function testValidAddress(): void
{
    $this->assertTrue(Validator::isValidEthereumAddress('0x' . str_repeat('a', 40)));
}

public function testInvalidAddressLength(): void
{
    $this->assertFalse(Validator::isValidEthereumAddress('0x123'));
}

public function testInvalidAddressPrefix(): void
{
    $this->assertFalse(Validator::isValidEthereumAddress(str_repeat('a', 42)));
}
```

### 2. Arrange-Act-Assert Pattern

```php
public function testPaymentVerification(): void
{
    // Arrange
    $facilitator = $this->createMock(FacilitatorClient::class);
    $handler = new PaymentHandler($facilitator);
    $requirements = $this->createPaymentRequirements();
    
    // Act
    $result = $handler->processPayment($_SERVER, $requirements);
    
    // Assert
    $this->assertFalse($result['verified']);
}
```

### 3. Mock External Dependencies

```php
// ✅ CORRECT - Mock facilitator
$facilitator = $this->createMock(FacilitatorClient::class);
$facilitator->expects($this->once())
    ->method('verify')
    ->willReturn(new VerifyResponse(true, null));

// ❌ WRONG - Real API calls in tests
$facilitator = new FacilitatorClient('https://real-api.com');
```

---

## 🚫 Common Mistakes to Avoid

### 1. Inventing Protocol Features

```php
// ❌ WRONG - Range scheme doesn't exist in x402 v1
public function createRangePayment($min, $max) {
    return new PaymentRequirements(
        scheme: 'range',  // NOT IN SPEC!
        minAmount: $min,
        maxAmount: $max
    );
}
```

**If you need a feature not in the spec:**
1. Check if it's in the official x402 roadmap
2. Document it as "experimental" or "extension"
3. Make it opt-in, not default
4. Clearly mark it as non-standard

### 2. Skipping Validation

```php
// ❌ WRONG - No validation
public function setAmount(string $amount): void {
    $this->amount = $amount;  // Could be "abc"!
}

// ✅ CORRECT - Always validate
public function setAmount(string $amount): void {
    if (!Validator::isValidUintString($amount)) {
        throw new ValidationException('Invalid amount');
    }
    $this->amount = $amount;
}
```

### 3. Exposing Sensitive Data

```php
// ❌ WRONG - Leaking facilitator errors
throw new FacilitatorException(
    'Facilitator error: ' . $response->error  // Could expose internal details
);

// ✅ CORRECT - Sanitize errors
$this->logger->error('Facilitator verification failed', [
    'reason' => $response->invalidReason
]);

throw new FacilitatorException(
    'Payment verification failed',
    ErrorCodes::FACILITATOR_VERIFICATION_FAILED
);
```

### 4. Inconsistent Network IDs

```php
// ✅ CORRECT - Use official network IDs
'base-mainnet'
'base-sepolia'
'ethereum-mainnet'
'ethereum-sepolia'
'solana-mainnet'
'solana-devnet'

// ❌ WRONG - Custom network IDs
'base'
'base-testnet'
'eth-mainnet'
'sol'
```

### 5. Magic Numbers

```php
// ❌ WRONG
if (strlen($address) !== 42) {
    throw new ValidationException('Invalid address');
}

// ✅ CORRECT
private const EVM_ADDRESS_LENGTH = 42;
private const EVM_ADDRESS_HEX_LENGTH = 40; // without 0x

if (strlen($address) !== self::EVM_ADDRESS_LENGTH) {
    throw new ValidationException('Invalid address');
}
```

---

## 📚 Reference Implementation

### Complete Payment Flow

```php
<?php

declare(strict_types=1);

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Nonce\RedisNonceTracker;
use X402\RateLimit\RedisRateLimiter;
use X402\Exceptions\PaymentRequiredException;

// 1. Setup (do this once)
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$facilitator = FacilitatorClient::payai(
    apiKey: getenv('FACILITATOR_API_KEY')
);

$handler = new PaymentHandler(
    facilitator: $facilitator,
    autoSettle: true,
    validBeforeBufferSeconds: 6,
    nonceTracker: new RedisNonceTracker($redis, 'myapp'),
    rateLimiter: new RedisRateLimiter($redis, maxAttempts: 10, decaySeconds: 60),
    logger: $logger
);

// 2. Define payment requirements
$requirements = $handler->createPaymentRequirements(
    payTo: '0xYourAddress',
    amount: '1000000',  // 1 USDC (6 decimals)
    resource: 'https://api.example.com/data',
    description: 'Premium API access',
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',  // USDC on Base
    network: 'base-mainnet',
    extra: [
        'name' => 'USD Coin',
        'version' => '2'
    ],
    id: 'payment-' . uniqid()
);

// 3. Process payment
try {
    $result = $handler->processPayment($_SERVER, $requirements);
    
    if ($result['verified']) {
        // Payment successful
        http_response_code(200);
        
        if ($result['settlement']) {
            header('X-Payment-Response: ' . 
                $handler->createPaymentResponseHeader($result['settlement']));
        }
        
        echo json_encode(['data' => 'Your premium content']);
    } else {
        // Payment required
        $response = $handler->createPaymentRequiredResponse($requirements);
        $response->send();
    }
} catch (PaymentRequiredException $e) {
    // Invalid payment
    http_response_code(402);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

## 🔍 Code Review Checklist

Before suggesting changes, verify:

- [ ] Feature exists in official x402 specification
- [ ] Strict types declared (`declare(strict_types=1);`)
- [ ] All parameters have type hints
- [ ] Readonly used for immutable properties
- [ ] Proper error codes from `ErrorCodes` class
- [ ] Input validation before processing
- [ ] PHPDoc comments for public methods
- [ ] No magic numbers (use constants)
- [ ] Tests included for new features
- [ ] No sensitive data in error messages
- [ ] Facilitator required for cryptographic operations
- [ ] Network IDs match x402 specification
- [ ] HTTP 402 headers set correctly
- [ ] EIP-712 extra field included for EVM

---

## 📖 Documentation Requirements

When adding features, update:

1. **PHPDoc comments** - All public methods
2. **README.md** - Usage examples
3. **CHANGELOG.md** - Version history
4. **examples/** - Working code examples
5. **Tests** - Comprehensive test coverage

---

## 🤝 Protocol Evolution

If the x402 protocol adds new features (e.g., `range` scheme):

1. **Verify in official spec** - Check GitHub repo
2. **Update types** - Add new scheme types
3. **Add validation** - Scheme-specific validation
4. **Update error codes** - New error cases
5. **Add tests** - Comprehensive coverage
6. **Update docs** - README, examples, CHANGELOG
7. **Maintain backwards compatibility** - Don't break existing code

---

## 🆘 When in Doubt

**Ask these questions:**

1. Is this feature in the official x402 specification?
2. Does this maintain backwards compatibility?
3. Is this properly validated and secured?
4. Are there tests covering this code?
5. Does this follow PSR-12 coding standards?
6. Is this documented with PHPDoc?

**If unsure, err on the side of:**
- Stricter validation
- Better error messages
- More comprehensive tests
- Clearer documentation

---

## 📞 Get Help

- **x402 Protocol**: https://github.com/coinbase/x402
- **x402 Docs**: https://docs.cdp.coinbase.com/x402/welcome
- **x402 Discord**: https://discord.com/invite/cdp
- **This Project**: See CONTRIBUTING.md

---

**Remember**: We're building infrastructure for the future of internet payments. Protocol compliance and security are non-negotiable.
