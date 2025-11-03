# Changelog - x402-php Security and Protocol Compliance Fixes

## [2.0.0] - 2025-11-03

### 🚨 BREAKING CHANGES

- `PaymentHandler` constructor now accepts additional optional parameters for security features
- Production deployments now enforce facilitator requirement when `APP_ENV=production`
- `validBeforeBufferSeconds` is now validated (must be 0-300)

### 🔒 Major Security Enhancements

#### Added - Replay Attack Prevention
- **`NonceTrackerInterface`**: Interface for tracking used nonces
- **`RedisNonceTracker`**: Redis-based implementation preventing payment replay attacks
- Automatic nonce tracking in `PaymentHandler` when configured
- Nonce validation throws `NONCE_ALREADY_USED` error code

#### Added - DoS Protection
- **`RateLimiterInterface`**: Interface for rate limiting payment verification attempts
- **`RedisRateLimiter`**: Redis-based sliding window rate limiter
- Rate limiting in `processPayment()` method
- Returns HTTP 429 with `Retry-After` header when limit exceeded
- New error code: `RATE_LIMIT_EXCEEDED`

#### Added - Compliance & AML/KYC
- **`ComplianceCheckInterface`**: Interface for AML/KYC address screening
- **`ComplianceResult`**: Result object for compliance checks
- **`ComplianceException`**: Exception for blocked addresses
- Optional compliance check integration in payment verification
- New error code: `ADDRESS_BLOCKED`, `COMPLIANCE_CHECK_FAILED`

#### Added - Observability
- **`MetricsInterface`**: Interface for recording payment metrics
- PSR-3 logger support throughout payment flow
- Automatic metrics recording for:
  - Payment verification success/failure
  - Verification duration
  - Rate limit violations
  - Replay attack attempts
  - Compliance check results
- Detailed audit logging for all payment operations

#### Added - Production Hardening
- **Environment-based facilitator enforcement**: Automatically requires facilitator when `APP_ENV=production`
- **Input validation improvements**:
  - `sanitizeString()` now removes control characters and enforces max length
  - `sanitizeUrl()` enforces http/https schemes only
  - `isValidUintString()` validates against uint256 max value
  - New `validateEip712Domain()` ensures proper domain format
- **Error sanitization**: Facilitator errors no longer leak internal details
- **Connection timeout**: Added separate `connect_timeout` to HTTP client
- **Timing buffer validation**: Enforces 0-300 second range

#### Added - Default Facilitator
- **`FacilitatorClient::payai()`**: New factory method for PayAI facilitator
- **Default facilitator URL**: `https://facilitator.payai.network`
- Updated examples to use PayAI as default

### 📚 Documentation & Tooling

#### Added
- **`SECURITY_CHECKLIST.md`**: Comprehensive production deployment security guide
- **`bin/validate-production.php`**: Production readiness validation script that checks:
  - Environment configuration
  - Facilitator connectivity
  - Redis availability (for nonce tracking)
  - Rate limiting setup
  - PHP extensions
  - Security settings
- **`examples/production-setup.php`**: Complete production-ready example with all security features
- Enhanced README with security features and production setup guide

### 🐛 Bug Fixes

#### Fixed
- **HTTP 402 vs 401 status code**: `PaymentRequiredResponse::send()` now correctly emits headers before status code to prevent PHP from overriding 402 to 401 when `WWW-Authenticate` is present
- **Solana payment validation timing**: Now checks for facilitator before processing payload
- **Amount comparison**: Added validation that strings are valid uint256 before comparison
- **EIP-712 domain validation**: Now enforces non-empty name/version with length limits
- **Examples updated**: All examples now use `send()` method or correct header ordering

### 📦 Dependencies

#### Added
- `psr/log: ^3.0` (PSR-3 logger interface)

#### Suggested
- `ext-redis`: Required for `RedisNonceTracker` and `RedisRateLimiter`
- `monolog/monolog`: Recommended PSR-3 logger implementation

### 📝 New Error Codes

- `FACILITATOR_REQUIRED`: Facilitator is required but not configured
- `RATE_LIMIT_EXCEEDED`: Too many payment attempts
- `NONCE_ALREADY_USED`: Replay attack detected
- `INVALID_NONCE`: Nonce format is invalid
- `COMPLIANCE_CHECK_FAILED`: Compliance screening failed
- `ADDRESS_BLOCKED`: Address is sanctioned/blocked
- `INVALID_EIP712_DOMAIN`: EIP-712 domain parameters invalid

### 🔧 API Changes

#### `PaymentHandler::__construct()`
```php
// Before
public function __construct(
    private readonly ?FacilitatorClient $facilitator = null,
    private readonly bool $autoSettle = true,
    private readonly int $validBeforeBufferSeconds = 6
)

// After
public function __construct(
    private readonly ?FacilitatorClient $facilitator = null,
    private readonly bool $autoSettle = true,
    private readonly int $validBeforeBufferSeconds = 6,
    private readonly ?NonceTrackerInterface $nonceTracker = null,
    private readonly ?RateLimiterInterface $rateLimiter = null,
    private readonly ?ComplianceCheckInterface $complianceCheck = null,
    private readonly ?MetricsInterface $metrics = null,
    private readonly ?LoggerInterface $logger = null
)
```

#### `PaymentHandler::processPayment()`
```php
// Before
public function processPayment(array $headers, PaymentRequirements $requirements): array

// After
public function processPayment(
    array $headers, 
    PaymentRequirements $requirements,
    ?string $identifier = null  // For rate limiting
): array
```

### 🎯 Migration Guide

#### Upgrading from 1.x to 2.0

**Basic upgrade (no code changes required)**:
```bash
composer update mondb-dev/x402-php
```

**To enable new security features**:

1. Install Redis extension:
```bash
pecl install redis
```

2. Update your code:
```php
use X402\Nonce\RedisNonceTracker;
use X402\RateLimit\RedisRateLimiter;
use Monolog\Logger;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: new RedisNonceTracker($redis),
    rateLimiter: new RedisRateLimiter($redis),
    logger: new Logger('x402')
);
```

3. Validate production readiness:
```bash
php bin/validate-production.php
```

4. Review [SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md)

## [Unreleased] - 2025-10-28

### 🔐 Security Enhancements

#### Added
- **Facilitator requirement for Solana**: Library now requires a facilitator for all Solana transaction verification and will reject Solana payments without one
- **EIP-712 domain validation**: Added validation for required `name` and `version` fields in the `extra` parameter for EVM payments
- **Security documentation**: Created comprehensive `SECURITY.md` with security considerations, best practices, and limitations
- **In-code security warnings**: Added comments throughout `PaymentHandler` documenting signature verification delegation and security implications

#### Changed
- **Error codes**: Added `FACILITATOR_ERROR` and `FACILITATOR_VERIFICATION_FAILED` error codes for better error handling
- **All exceptions now use error codes**: Ensured consistent error code usage throughout `PaymentHandler`

### ✨ Protocol Compliance

#### Added
- **WWW-Authenticate header**: Added `getHeaders()` method to `PaymentRequiredResponse` that returns all required x402 protocol headers:
  - `WWW-Authenticate: X-Payment`
  - `Content-Type: application/json`
  - `X-Payment-Accept: <schemes>`
- **Updated examples**: Both `basic-usage.php` and `solana-usage.php` now use the correct 402 response headers

### ⚙️ Configuration Improvements

#### Added
- **Configurable timing buffers**: Added `validBeforeBufferSeconds` parameter to `PaymentHandler` constructor (default: 6 seconds)
- **Network-specific timing documentation**: Added comments explaining appropriate buffer values for different networks:
  - EVM L2s (Base, Optimism, Arbitrum): 6s for ~2s blocks
  - Ethereum mainnet: 36s for ~12s blocks
  - Solana: 2s for ~0.4s slots

### 🌐 Network Support

#### Added
- **Expanded network support** in `Validator::SUPPORTED_NETWORKS`:
  - Ethereum: mainnet, sepolia, holesky
  - Base: mainnet, sepolia
  - Optimism: mainnet, sepolia
  - Arbitrum: mainnet, sepolia
  - Polygon: mainnet, amoy
  - Solana: mainnet, devnet, testnet

### 🧪 Testing Improvements

#### Added
- **Solana payment tests**: Added comprehensive tests for Solana payment flows in `PaymentHandlerTest`:
  - Empty transaction validation
  - Invalid base64 validation
  - Facilitator requirement enforcement
- **EIP-712 validation tests**: Added tests for missing domain `name` and `version` parameters
- **FacilitatorClient tests**: Created `tests/Facilitator/FacilitatorClientTest.php` with structural tests

### 📚 Documentation

#### Added
- **Security section in README**: Extensive security documentation including:
  - Cryptographic signature verification delegation
  - Solana transaction validation requirements
  - Production configuration examples
  - Network-specific timing recommendations
  - Security best practices
- **Updated README**: Enhanced security warnings and facilitator requirements throughout

#### Changed
- **Inline documentation**: Improved comments in `PaymentHandler` to clearly document:
  - What is validated locally vs. by facilitator
  - Security implications of each validation step
  - Required dependencies for local verification

### 🔧 Code Quality

#### Changed
- **Magic numbers removed**: Replaced hardcoded `6` with configurable `validBeforeBufferSeconds`
- **Consistent error handling**: All exceptions now include appropriate error codes from `ErrorCodes` class
- **Type safety**: Ensured all type hints are correct and consistent

## Implementation Details

### Files Modified

1. **src/Types/PaymentRequiredResponse.php**
   - Added `getHeaders()` method for x402 protocol-compliant headers

2. **src/Middleware/PaymentHandler.php**
   - Added `validBeforeBufferSeconds` constructor parameter
   - Added EIP-712 domain validation
   - Added Solana facilitator requirement check
   - Updated error codes for facilitator exceptions
   - Added comprehensive security documentation comments
   - Fixed timing buffer to use configurable value

3. **src/Exceptions/ErrorCodes.php**
   - Added `FACILITATOR_ERROR` constant
   - Added `FACILITATOR_VERIFICATION_FAILED` constant

4. **src/Validation/Validator.php**
   - Expanded `SUPPORTED_NETWORKS` array with all major networks

5. **tests/Middleware/PaymentHandlerTest.php**
   - Added `ExactSvmPayload` import
   - Updated `createRequirements()` to include EIP-712 domain parameters
   - Added Solana test helper methods
   - Added 4 new Solana-specific test cases
   - Added 2 new EIP-712 validation test cases

6. **examples/basic-usage.php**
   - Updated 402 response to use `getHeaders()` method

7. **examples/solana-usage.php**
   - Updated 402 response to use `getHeaders()` method

### Files Created

1. **tests/Facilitator/FacilitatorClientTest.php**
   - Comprehensive test suite for FacilitatorClient
   - Tests for URL validation, timeout, API keys
   - Placeholder tests for HTTP mocking

2. **SECURITY.md**
   - Complete security documentation
   - Best practices and guidelines
   - Known limitations
   - Compliance considerations

3. **CHANGELOG.md** (this file)
   - Documentation of all changes

## Breaking Changes

### ⚠️ Potential Breaking Changes

1. **Solana payments without facilitator**: Solana payments will now throw an exception if no facilitator is configured. This is by design for security.

2. **EIP-712 domain parameters required**: EVM payments will fail validation if `extra.name` or `extra.version` are missing. Update your code to include these:
   ```php
   extra: [
       'name' => 'USD Coin',
       'version' => '2'
   ]
   ```

3. **Header method usage**: If you're manually setting headers, update to use the new method:
   ```php
   // Old way (still works but not recommended)
   http_response_code(402);
   header('Content-Type: application/json');

   // New way (protocol compliant)
   $paymentRequiredResponse->send();
   ```

## Migration Guide

### For Existing Users

1. **Add EIP-712 parameters** to all EVM payment requirements:
   ```php
   extra: [
       'name' => 'Token Name',    // From ERC-20 contract
       'version' => 'Version'     // From ERC-20 contract
   ]
   ```

2. **Update 402 responses** to use proper headers:
   ```php
   $handler->createPaymentRequiredResponse($requirements)->send();
   ```

3. **Configure timing buffers** for your network:
   ```php
   // For Base/Optimism/Arbitrum
   $handler = new PaymentHandler($facilitator, true, 6);
   
   // For Ethereum mainnet
   $handler = new PaymentHandler($facilitator, true, 36);
   
   // For Solana
   $handler = new PaymentHandler($facilitator, true, 2);
   ```

4. **Review security documentation** in SECURITY.md and README.md

## Testing

All existing tests pass with these changes. New tests added:
- 4 Solana-specific payment flow tests
- 2 EIP-712 validation tests  
- 10 FacilitatorClient structural tests

Run tests with:
```bash
./vendor/bin/phpunit
```

## Future Improvements

These fixes address critical security and protocol compliance issues. Future improvements may include:

1. **Local signature verification** (optional): Add EIP-712 hashing and ECDSA recovery
2. **Solana transaction parsing** (optional): Add SPL token instruction parsing
3. **Range scheme support**: Implement flexible payment amounts
4. **Nonce tracking utilities**: Built-in replay attack prevention
5. **Rate limiting middleware**: Built-in DoS protection
6. **Payment analytics**: Track and analyze payment patterns

## References

- [x402 Protocol Specification](https://github.com/coinbase/x402)
- [x402 Documentation](https://x402.gitbook.io/x402)
- [EIP-712: Typed Structured Data Hashing](https://eips.ethereum.org/EIPS/eip-712)
- [EIP-3009: Transfer With Authorization](https://eips.ethereum.org/EIPS/eip-3009)

---

**Review Status**: ✅ All critical and high-priority issues addressed
**Production Ready**: ⚠️ With facilitator configured - see SECURITY.md
