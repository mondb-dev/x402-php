# Troubleshooting Guide

Common issues and solutions for the x402-php library.

## Common Issues

### 1. HTTP 402 vs 401 Status Code Issue

**Problem**: PHP automatically changes HTTP 402 to 401 when `WWW-Authenticate` header is set after the status code.

**Solution**: Always use the `send()` method from `PaymentRequiredResponse` or set headers before status code:

```php
// ❌ WRONG - Returns 401 instead of 402
http_response_code(402);
header('WWW-Authenticate: X-Payment');
echo json_encode($response);

// ✅ CORRECT - Use the send() method
$paymentRequiredResponse->send();

// ✅ CORRECT - Set headers before status code
header('WWW-Authenticate: X-Payment');
http_response_code(402);
echo json_encode($response);
```

### 2. Signature Verification Failures

**Problem**: Payment verification fails with EIP-712 signature errors.

**Causes**:
- Incorrect `name` or `version` in `extra` field
- Token address mismatch
- Network mismatch

**Solutions**:

```php
// Ensure extra field has correct EIP-712 domain parameters
$requirements = $handler->createPaymentRequirements(
    // ... other params ...
    extra: [
        'name' => 'USD Coin',      // Must match token contract
        'version' => '2'            // Must match token contract
    ]
);

// For USDC on Base:
// name: "USD Coin"
// version: "2"

// Use TokenValidator to check known tokens
use X402\Validation\TokenValidator;

$tokenInfo = TokenValidator::getKnownToken('base-mainnet', '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913');
// Returns: ['name' => 'USD Coin', 'version' => '2', 'symbol' => 'USDC', 'decimals' => 6]
```

### 3. Nonce Reuse Errors

**Problem**: "Nonce has already been used (replay attack detected)"

**Causes**:
- Same payment being submitted twice
- Race condition between concurrent requests
- Nonce not cleared after test

**Solutions**:

```php
// For testing, clear nonces:
$nonceTracker->remove($nonce);

// Ensure atomic nonce checking with Redis:
$nonceTracker = new RedisNonceTracker($redis, 'myapp');
// Uses SET NX internally for atomic check-and-set

// Check nonce expiry matches validBefore:
$ttl = max(60, $validBefore - time());
$nonceTracker->markUsed($nonce, $ttl);
```

### 4. Rate Limit Exceeded

**Problem**: "Rate limit exceeded, retry after X seconds"

**Causes**:
- Too many payment attempts from same IP
- DoS attack prevention triggered
- Testing without rate limiter bypass

**Solutions**:

```php
// For testing, increase limits:
$rateLimiter = new RedisRateLimiter(
    $redis,
    maxAttempts: 100,      // Higher limit for testing
    decaySeconds: 60
);

// Or reset for specific identifier:
$rateLimiter->reset($ipAddress);

// In production, implement proper retry logic:
if ($rateLimiter->tooManyAttempts($identifier)) {
    $retryAfter = $rateLimiter->availableIn($identifier);
    throw new PaymentRequiredException(
        "Too many attempts, retry after {$retryAfter}s",
        ErrorCodes::RATE_LIMIT_EXCEEDED
    );
}
```

### 5. Clock Drift Issues

**Problem**: "Payment authorization expired or expiring soon" when it shouldn't be.

**Causes**:
- Client and server clocks out of sync
- Timezone differences
- System time not synchronized

**Solutions**:

```php
// Increase clock drift tolerance:
$handler = new PaymentHandler(
    facilitator: $facilitator,
    clockDriftToleranceSeconds: 60  // Allow 60s clock drift
);

// Ensure system time is synchronized:
// On Linux: timedatectl set-ntp true
// On macOS: sudo sntp -sS time.apple.com

// Check current settings:
echo "Server time: " . date('c') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
```

### 6. Redis Connection Issues

**Problem**: "Redis connection failed" or nonce/rate limit errors

**Causes**:
- Redis not running
- Wrong connection parameters
- Network firewall blocking connection

**Solutions**:

```php
// Test Redis connection:
$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379);
    $redis->ping();
    echo "Redis connected successfully\n";
} catch (Exception $e) {
    echo "Redis error: " . $e->getMessage() . "\n";
}

// Check Redis is running:
// redis-cli ping
// Should return: PONG

// Use production validator:
php bin/validate-production.php
```

### 7. Facilitator Connection Errors

**Problem**: "Payment verification failed: facilitator error"

**Causes**:
- Facilitator service down
- Invalid API key
- Network connectivity issues
- Timeout

**Solutions**:

```php
// Increase timeout for slow networks:
$facilitator = FacilitatorClient::payai(
    apiKey: getenv('FACILITATOR_API_KEY'),
    timeout: 60  // 60 second timeout
);

// Add circuit breaker for resilience:
use X402\CircuitBreaker\CircuitBreaker;

$circuitBreaker = new CircuitBreaker(
    failureThreshold: 5,
    recoveryTimeout: 60,
    successThreshold: 2
);

try {
    $result = $circuitBreaker->call(function() use ($facilitator, $paymentHeader, $requirements) {
        return $facilitator->verify($paymentHeader, $requirements);
    });
} catch (CircuitOpenException $e) {
    // Circuit is open, fail fast
    echo "Facilitator temporarily unavailable\n";
}

// Test facilitator connectivity:
try {
    $config = $facilitator->getSupported();
    echo "Facilitator connected, supports " . count($config->schemes) . " schemes\n";
} catch (Exception $e) {
    echo "Facilitator error: " . $e->getMessage() . "\n";
}
```

### 8. Amount Overflow Issues

**Problem**: "Amount overflow: result exceeds uint256 max"

**Causes**:
- Amount exceeds maximum uint256 value
- Invalid amount format
- Arithmetic overflow in calculations

**Solutions**:

```php
// Use Validator for safe arithmetic:
use X402\Validation\Validator;

// Check amount is valid:
if (!Validator::isValidUintString($amount)) {
    throw new ValidationException('Invalid amount');
}

// Safe addition:
$total = Validator::safeAddUint256($amount1, $amount2);

// Safe multiplication:
$result = Validator::safeMulUint256($amount, $multiplier);

// Install bcmath extension for full uint256 support:
// sudo apt-get install php-bcmath (Ubuntu/Debian)
// sudo yum install php-bcmath (CentOS/RHEL)
// brew install php --with-bcmath (macOS)
```

### 9. Missing PHP Extensions

**Problem**: "bcmath extension required for safe uint256 operations"

**Solutions**:

```bash
# Ubuntu/Debian
sudo apt-get install php8.1-bcmath php8.1-redis

# CentOS/RHEL
sudo yum install php-bcmath php-redis

# macOS
brew install php
pecl install redis

# Check installed extensions
php -m | grep -E "(bcmath|redis)"

# Enable in php.ini
extension=bcmath
extension=redis
```

### 10. Solana Transaction Validation Issues

**Problem**: Solana payments fail validation

**Causes**:
- Facilitator required for Solana
- Invalid transaction format
- Wrong ATA (Associated Token Account)

**Solutions**:

```php
// ALWAYS use facilitator for Solana:
$facilitator = FacilitatorClient::payai(apiKey: getenv('FACILITATOR_API_KEY'));

// This is enforced in production:
$appEnv = getenv('APP_ENV') ?: 'production';
if ($appEnv === 'production' && $facilitator === null) {
    throw new RuntimeException('Facilitator REQUIRED for Solana in production');
}

// Ensure transaction is base64 encoded:
$transaction = base64_encode($rawTransaction);

// Include feePayer in extra if needed:
$extra = [
    'feePayer' => 'SolanaAddressHere...'
];
```

## Performance Issues

### Slow Payment Verification

**Symptoms**: Payment verification takes > 5 seconds

**Solutions**:

```php
// Enable caching for facilitator config:
use X402\Facilitator\CachedFacilitatorClient;

$cachedFacilitator = new CachedFacilitatorClient(
    $facilitator,
    cacheTtl: 300  // Cache for 5 minutes
);

// Use metrics to identify bottlenecks:
$metrics = new DefaultMetrics();
$handler = new PaymentHandler(
    facilitator: $facilitator,
    metrics: $metrics
);

// After processing:
$stats = $metrics->getMetrics();
print_r($stats['timings']);
```

### High Redis Memory Usage

**Symptoms**: Redis memory grows over time

**Solutions**:

```bash
# Check Redis memory usage
redis-cli INFO memory

# Nonces and rate limits should have TTL
# Check keys without expiry:
redis-cli --scan --pattern "x402:*" | xargs -L 1 redis-cli TTL

# All should return positive number or -2 (doesn't exist)
# If returning -1, key has no expiry - this is a bug!

# Manually set TTL if needed:
redis-cli EXPIRE "x402:nonce:namespace:0x..." 3600
```

## Security Issues

### Information Leakage

**Problem**: Error messages expose sensitive information

**Solution**: Error sanitization is built-in:

```php
// Facilitator errors are automatically sanitized:
// - API keys redacted
// - IP addresses redacted
// - URLs redacted
// - Message length limited

// For custom error handling:
private function sanitizeError(Exception $e): string
{
    $message = $e->getMessage();
    $message = preg_replace('/api[_-]?key[=:]\s*\S+/i', 'api_key=[REDACTED]', $message);
    return substr($message, 0, 256);
}
```

### Replay Attacks

**Problem**: Same payment used multiple times

**Solution**: Use nonce tracker (already implemented):

```php
// Nonce tracking prevents replay attacks:
$nonceTracker = new RedisNonceTracker($redis, 'myapp');
$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker
);

// Nonces are automatically:
// 1. Checked before processing
// 2. Marked as used atomically (SET NX)
// 3. Expired after validBefore timestamp
```

## Getting Help

If you're still experiencing issues:

1. **Check logs**: Enable PSR-3 logger to see detailed debug info
2. **Run health check**: `php bin/validate-production.php`
3. **Check GitHub issues**: https://github.com/mondb-dev/x402-php/issues
4. **Review docs**: https://docs.cdp.coinbase.com/x402/welcome
5. **Test with examples**: Run examples in `examples/` directory

### Debugging Checklist

- [ ] PHP version >= 8.1
- [ ] Required extensions installed (json, curl)
- [ ] Recommended extensions installed (redis, bcmath)
- [ ] Redis connection working
- [ ] Facilitator API key configured
- [ ] Network connectivity to facilitator
- [ ] System time synchronized
- [ ] Nonce tracker configured
- [ ] Rate limiter configured
- [ ] Logs enabled for debugging
