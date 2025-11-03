# Security Checklist for Production Deployment

This document outlines **mandatory** and **recommended** security measures for deploying x402-php in production environments.

> ⚠️ **CRITICAL**: Do NOT deploy to production without implementing ALL mandatory requirements.

---

## ✅ Mandatory Requirements

### 1. Facilitator Configuration

**Status**: 🔴 **REQUIRED**

The library **CANNOT perform cryptographic signature verification** locally. A facilitator is absolutely required for production.

**Implementation**:

```php
use X402\Facilitator\FacilitatorClient;

// Option 1: Use PayAI Facilitator (default)
$facilitator = FacilitatorClient::payai(
    apiKey: getenv('FACILITATOR_API_KEY'),
    timeout: 30
);

// Option 2: Use Coinbase Facilitator
$facilitator = FacilitatorClient::coinbase(
    apiKey: getenv('FACILITATOR_API_KEY'),
    timeout: 30
);

// Option 3: Self-hosted facilitator
$facilitator = FacilitatorClient::selfHosted(
    baseUrl: getenv('FACILITATOR_BASE_URL'),
    apiKey: getenv('FACILITATOR_API_KEY')
);
```

**Environment Variables**:
```bash
FACILITATOR_BASE_URL=https://facilitator.payai.network
FACILITATOR_API_KEY=your_api_key_here
APP_ENV=production
```

**Validation**:
- ✅ Facilitator URL must use HTTPS
- ✅ API key must be configured (check facilitator requirements)
- ✅ Test connection before going live

---

### 2. Nonce Tracking (Replay Attack Prevention)

**Status**: 🔴 **REQUIRED**

Without nonce tracking, the same payment can be replayed infinitely. This is a **critical security vulnerability**.

**Implementation**:

```php
use X402\Nonce\RedisNonceTracker;
use Redis;

// Configure Redis
$redis = new Redis();
$redis->connect(
    getenv('REDIS_HOST') ?: '127.0.0.1',
    (int)(getenv('REDIS_PORT') ?: 6379)
);

// Optional: Authenticate
if ($password = getenv('REDIS_PASSWORD')) {
    $redis->auth($password);
}

// Optional: Select database
$redis->select((int)(getenv('REDIS_DB') ?: 0));

// Create nonce tracker
$nonceTracker = new RedisNonceTracker($redis, namespace: 'myapp');

// Use in PaymentHandler
$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker
);
```

**Environment Variables**:
```bash
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password
REDIS_DB=0
```

**Validation**:
- ✅ Redis extension installed (`php -m | grep redis`)
- ✅ Redis server is running and accessible
- ✅ Test nonce tracking before deployment

---

### 3. Rate Limiting

**Status**: 🟡 **HIGHLY RECOMMENDED**

Prevents DoS attacks and brute force attempts on payment verification.

**Implementation**:

```php
use X402\RateLimit\RedisRateLimiter;

$rateLimiter = new RedisRateLimiter(
    redis: $redis,
    maxAttempts: 10,      // Max attempts per window
    decaySeconds: 60,     // Time window
    namespace: 'myapp'
);

$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker,
    rateLimiter: $rateLimiter
);
```

**Recommended Limits**:
- Development: 100 attempts / 60 seconds
- Production: 10 attempts / 60 seconds
- Adjust based on your traffic patterns

**Validation**:
- ✅ Test rate limiting with multiple rapid requests
- ✅ Verify 429 responses are returned when limit exceeded

---

### 4. Logging and Audit Trails

**Status**: 🟡 **HIGHLY RECOMMENDED**

Essential for debugging, security audits, and compliance.

**Implementation**:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('x402');
$logger->pushHandler(
    new RotatingFileHandler(
        '/var/log/x402/payments.log',
        maxFiles: 30,
        level: Logger::INFO
    )
);

$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker,
    rateLimiter: $rateLimiter,
    logger: $logger
);
```

**What to Log**:
- ✅ All payment verification attempts (success and failure)
- ✅ Nonce usage (for replay attack detection)
- ✅ Rate limit violations
- ✅ Compliance check results
- ✅ Facilitator errors

**Security Considerations**:
- ⚠️ Do NOT log full payment details (PII)
- ⚠️ Do NOT log API keys or secrets
- ✅ Log transaction IDs, timestamps, and outcomes only
- ✅ Implement log rotation (prevent disk space exhaustion)
- ✅ Secure log files (proper permissions)

---

## 🔐 Recommended Security Measures

### 5. Compliance Checks (AML/KYC)

**Status**: ⚪ **OPTIONAL** (Required for regulated industries)

Integrate with compliance providers to screen addresses.

**Implementation**:

```php
use X402\Compliance\ComplianceCheckInterface;
use X402\Compliance\ComplianceResult;

class MyComplianceCheck implements ComplianceCheckInterface
{
    public function __construct(private readonly YourComplianceProvider $provider) {}
    
    public function checkAddress(string $address, string $network): ComplianceResult
    {
        $result = $this->provider->screenAddress($address);
        
        if ($result['is_sanctioned'] || $result['risk_level'] === 'high') {
            return new ComplianceResult(
                isBlocked: true,
                reason: 'Address flagged by compliance screening',
                metadata: ['risk_score' => $result['score']]
            );
        }
        
        return new ComplianceResult(isBlocked: false);
    }
}

$complianceCheck = new MyComplianceCheck($yourProvider);

$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker,
    rateLimiter: $rateLimiter,
    logger: $logger,
    complianceCheck: $complianceCheck
);
```

**Providers**:
- [Chainalysis](https://www.chainalysis.com/)
- [Elliptic](https://www.elliptic.co/)
- [TRM Labs](https://www.trmlabs.com/)
- [Sardine](https://www.sardine.ai/)

---

### 6. Metrics and Monitoring

**Status**: ⚪ **OPTIONAL** (Recommended for production)

Track payment success rates, latency, and errors.

**Implementation**:

```php
use X402\Metrics\MetricsInterface;

class PrometheusMetrics implements MetricsInterface
{
    public function incrementCounter(string $metric, array $tags = []): void
    {
        // Integrate with your metrics system
        $this->prometheus->increment("x402_{$metric}", $tags);
    }
    
    public function recordTiming(string $metric, float $duration, array $tags = []): void
    {
        $this->prometheus->observe("x402_{$metric}_duration", $duration, $tags);
    }
    
    public function recordGauge(string $metric, float $value, array $tags = []): void
    {
        $this->prometheus->set("x402_{$metric}", $value, $tags);
    }
}

$metrics = new PrometheusMetrics($prometheusRegistry);

$handler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker,
    rateLimiter: $rateLimiter,
    logger: $logger,
    metrics: $metrics
);
```

**Key Metrics to Track**:
- `payment.verification.success` - Successful verifications
- `payment.verification.failure` - Failed verifications (by reason)
- `payment.verification.duration` - Verification latency
- `payment.rate_limit.exceeded` - Rate limit violations
- `payment.nonce_replay.detected` - Replay attack attempts

---

## 🛡️ Infrastructure Security

### 7. PHP HTTP Status Code Quirk (CRITICAL)

**Issue**: PHP automatically overrides HTTP 402 to 401 when `WWW-Authenticate` header is set AFTER the status code.

**Solution**: Always use the `send()` method or set headers before status code.

```php
// ❌ WRONG - PHP will return 401 instead of 402
http_response_code(402);
header('WWW-Authenticate: X-Payment');
header('Content-Type: application/json');
echo json_encode($response);

// ✅ CORRECT - Set headers first, then status code
header('WWW-Authenticate: X-Payment');
header('Content-Type: application/json');
http_response_code(402);
echo json_encode($response);

// ✅ BEST - Use the send() method (handles this automatically)
$paymentRequired = $handler->createPaymentRequiredResponse($requirements);
$paymentRequired->send();
```

**Why this matters**: If your API returns 401 instead of 402, clients will treat it as an authentication error instead of a payment requirement, breaking the x402 protocol.

**Validation**: Test your 402 responses with curl:
```bash
curl -I https://your-api.com/endpoint
# Should show: HTTP/1.1 402 Payment Required
# NOT: HTTP/1.1 401 Unauthorized
```

---

### 8. Network Security

**Checklist**:
- ✅ Use HTTPS for all API endpoints
- ✅ Configure proper CORS headers
- ✅ Implement IP whitelisting (if applicable)
- ✅ Use firewall rules to restrict Redis access
- ✅ Deploy behind CDN/WAF (Cloudflare, AWS WAF, etc.)

### 9. Environment Configuration

**php.ini Settings**:
```ini
display_errors = Off
expose_php = Off
max_execution_time = 30
memory_limit = 128M
post_max_size = 1M
upload_max_filesize = 1M
```

**Environment Variables** (never commit to git):
```bash
# Required
FACILITATOR_BASE_URL=https://facilitator.payai.network
FACILITATOR_API_KEY=your_api_key
APP_ENV=production

# Redis (for nonce tracking)
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=secure_password
REDIS_DB=0

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=10
RATE_LIMIT_DECAY_SECONDS=60

# Logging
LOG_LEVEL=info
LOG_PATH=/var/log/x402/payments.log
```

### 10. Dependency Management

**Checklist**:
- ✅ Run `composer install --no-dev` in production
- ✅ Keep dependencies up to date: `composer update`
- ✅ Use `composer.lock` to lock versions
- ✅ Audit dependencies: `composer audit`
- ✅ Remove unused dependencies

### 11. Code Security

**Checklist**:
- ✅ Enable opcache in production
- ✅ Run PHPStan: `vendor/bin/phpstan analyse`
- ✅ Set proper file permissions (644 for files, 755 for directories)
- ✅ Disable directory listing in web server
- ✅ Keep PHP version updated (security patches)

---

## 🧪 Testing Requirements

### Before Deployment

**Test Scenarios**:

1. **Valid Payment Flow**
   ```bash
   # Test with valid payment header
   curl -X POST https://your-api.com/pay \
     -H "X-Payment: base64_encoded_payment_here" \
     -H "Content-Type: application/json"
   ```

2. **Replay Attack Test**
   ```bash
   # Submit same payment twice
   # Second attempt should fail with NONCE_ALREADY_USED
   ```

3. **Rate Limit Test**
   ```bash
   # Send 15 requests rapidly
   # Should get 429 after 10 attempts
   ```

4. **Expired Payment Test**
   ```bash
   # Submit payment with expired validBefore
   # Should fail with INVALID_EVM_VALID_BEFORE
   ```

5. **Invalid Signature Test**
   ```bash
   # Submit payment with corrupted signature
   # Should fail with facilitator verification error
   ```

### Production Validation Script

Run the included validator before deploying:

```bash
php bin/validate-production.php
```

This checks:
- ✅ Environment configuration
- ✅ Facilitator connectivity
- ✅ Redis availability
- ✅ PHP extensions
- ✅ Composer dependencies
- ✅ Security settings

---

## 📊 Monitoring and Alerts

### Set Up Alerts For:

1. **High Error Rate**
   - Alert when verification failure rate > 10%
   - Indicates potential attack or misconfiguration

2. **Rate Limit Violations**
   - Alert when rate limit exceeded > 100 times/hour
   - Indicates DoS attempt

3. **Nonce Replay Attempts**
   - Alert on ANY nonce replay detection
   - Indicates active attack

4. **Facilitator Downtime**
   - Alert when facilitator errors > 5%
   - Critical for payment processing

5. **High Latency**
   - Alert when p95 verification time > 2 seconds
   - May indicate network issues

---

## 🚨 Incident Response

### If You Detect an Attack:

1. **Immediate Actions**:
   - Reduce rate limits (e.g., 5 attempts/minute)
   - Enable stricter logging
   - Review recent payment logs

2. **Investigation**:
   - Check for replay attacks in logs
   - Identify attacking IP addresses
   - Verify facilitator is responding correctly

3. **Mitigation**:
   - Block attacking IPs at firewall level
   - Temporarily increase rate limits if false positive
   - Contact facilitator support if needed

4. **Post-Incident**:
   - Document the incident
   - Update security measures
   - Review and improve monitoring

---

## 📚 Additional Resources

- [x402 Protocol Documentation](https://docs.cdp.coinbase.com/x402/welcome)
- [x402 GitHub Repository](https://github.com/coinbase/x402)
- [OWASP API Security Top 10](https://owasp.org/www-project-api-security/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

---

## ✅ Pre-Deployment Checklist

Before deploying to production, verify:

- [ ] Facilitator configured and tested
- [ ] Nonce tracker implemented (Redis)
- [ ] Rate limiting enabled
- [ ] Logging configured with rotation
- [ ] Metrics/monitoring set up
- [ ] All environment variables set
- [ ] `composer install --no-dev` run
- [ ] PHPStan analysis passed
- [ ] All tests passing
- [ ] `bin/validate-production.php` passed
- [ ] Backup/recovery plan in place
- [ ] Incident response plan documented
- [ ] Team trained on operations

---

## 📞 Support

For security issues, please refer to [SECURITY.md](SECURITY.md) for responsible disclosure guidelines.

For general questions:
- GitHub Issues: https://github.com/mondb-dev/x402-php/issues
- Documentation: See README.md

---

**Last Updated**: November 3, 2025
