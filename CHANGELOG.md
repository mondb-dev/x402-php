# Changelog - x402-php Security and Protocol Compliance Fixes

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
