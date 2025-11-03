# PHP HTTP 402 Status Code Fix

## Issue Description

PHP has a quirk where setting the `WWW-Authenticate` header causes the HTTP status code to be automatically overridden from 402 to 401 Unauthorized if `http_response_code(402)` is called **before** the header is set.

This breaks the x402 protocol because clients expect HTTP 402 Payment Required for payment requests, not 401 Unauthorized (which indicates authentication issues).

## Root Cause

When PHP's `http_response_code(402)` is called before setting headers that include `WWW-Authenticate`, PHP interprets this as an authentication challenge and automatically changes the status to 401. This is standard PHP behavior designed for traditional HTTP authentication.

## The Problem

```php
// ❌ WRONG - Results in HTTP 401 Unauthorized
http_response_code(402);
header('WWW-Authenticate: X-Payment');
header('Content-Type: application/json');
echo json_encode($response);

// Client sees:
// HTTP/1.1 401 Unauthorized  ← Wrong!
// WWW-Authenticate: X-Payment
// Content-Type: application/json
```

## The Solution

The `PaymentRequiredResponse::send()` method already handles this correctly by setting headers **before** the status code:

```php
// ✅ BEST - Use the send() method
$paymentRequired = $handler->createPaymentRequiredResponse($requirements);
$paymentRequired->send();

// Client sees:
// HTTP/1.1 402 Payment Required  ← Correct!
// WWW-Authenticate: X-Payment
// Content-Type: application/json
```

### Alternative: Manual Implementation

If you need to manually emit the response, set headers first:

```php
// ✅ CORRECT - Set headers first, then status code
header('WWW-Authenticate: X-Payment');
header('Content-Type: application/json');
header('X-Payment-Accept: exact');
http_response_code(402);
echo json_encode($response);

// Client sees:
// HTTP/1.1 402 Payment Required  ← Correct!
```

## Implementation Changes

### 1. Enhanced Documentation

**File**: `src/Types/PaymentRequiredResponse.php`

Added comprehensive documentation to the `send()` method explaining:
- The PHP quirk
- Why order matters
- Examples of correct vs incorrect usage
- Recommendation to always use `send()` method

### 2. Fixed Examples

**File**: `examples/production-setup.php`

Updated all 402 responses to use the `send()` method:

```php
// Before (WRONG)
http_response_code(402);
header('Content-Type: application/json');
header('X-Payment-Response: ' . base64_encode(json_encode($paymentRequired->toArray())));
echo json_encode(['success' => false, 'message' => 'Payment required']);

// After (CORRECT)
$paymentRequired->send();
exit;
```

### 3. Added Test Script

**File**: `examples/test-402-status.php`

Created a test script that:
- Tests the `send()` method (correct)
- Tests manual implementation with correct order (correct)
- Tests wrong order to demonstrate the bug (incorrect)
- Provides clear pass/fail output

**Usage**:
```bash
php examples/test-402-status.php
```

### 4. Updated Documentation

**File**: `README.md`

Added "Common Pitfalls" section explaining the issue.

**File**: `SECURITY_CHECKLIST.md`

Added dedicated section (Item 7) with validation instructions:

```bash
# Test your 402 responses
curl -I https://your-api.com/endpoint
# Should show: HTTP/1.1 402 Payment Required
# NOT: HTTP/1.1 401 Unauthorized
```

**File**: `CHANGELOG.md`

Documented the fix in version 2.0.0 release notes.

## Testing

### Quick Test

Run the test script:

```bash
php examples/test-402-status.php
```

Expected output:
```
✅ SUCCESS: Status code is 402 as expected
✅ SUCCESS: Manual method also works (status code is 402)
❌ EXPECTED FAILURE: Status code is 401 (PHP overrode to 401)
```

### Live Test

Start a PHP server and test with curl:

```bash
php -S localhost:8000 examples/test-402-status.php
```

In another terminal:
```bash
curl -I http://localhost:8000
```

Expected response:
```
HTTP/1.1 402 Payment Required
WWW-Authenticate: X-Payment
Content-Type: application/json
X-Payment-Accept: exact
```

**NOT**:
```
HTTP/1.1 401 Unauthorized  ← Wrong!
```

## Impact

### Before Fix
- ⚠️ Manual 402 responses in examples returned 401
- ⚠️ Developers could easily make this mistake
- ❌ x402 protocol broken (clients see 401 instead of 402)

### After Fix
- ✅ `send()` method handles header order correctly
- ✅ All examples use `send()` or correct order
- ✅ Comprehensive documentation warns about the issue
- ✅ Test script validates the fix
- ✅ x402 protocol compliance maintained

## Recommendations

1. **Always use `send()` method**:
   ```php
   $paymentRequired->send();
   ```

2. **If you must emit manually**, set headers first:
   ```php
   header('WWW-Authenticate: X-Payment');
   header('Content-Type: application/json');
   http_response_code(402);
   ```

3. **Test your endpoints**:
   ```bash
   curl -I https://your-api.com/endpoint
   ```

4. **Monitor in production**: Check your access logs for unexpected 401 responses on payment endpoints.

## Additional Resources

- [PHP header() documentation](https://www.php.net/manual/en/function.header.php)
- [x402 Protocol Specification](https://docs.cdp.coinbase.com/x402/welcome)
- [HTTP Status Code 402 Payment Required](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/402)

## Summary

The x402-php library now correctly handles the PHP HTTP 402 status code quirk. The `PaymentRequiredResponse::send()` method ensures headers are sent before the status code, preventing PHP from overriding 402 to 401. All examples and documentation have been updated to reflect this fix.

**Status**: ✅ FIXED and DOCUMENTED
