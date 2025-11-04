# Production Security Guide

This guide covers security best practices for deploying x402-php in production environments.

## ⚠️ Critical Security Requirements

### 1. HTTPS Only

**Always use HTTPS for facilitator URLs in production.**

```php
// ✅ CORRECT
$config = X402Config::coinbase('your-api-key');
// URL: https://facilitator.coinbase.com/api/v1

// ❌ WRONG - Will throw ConfigurationException in production
$config = X402Config::local('http://localhost:3000');
```

The library enforces HTTPS in production and will throw a `ConfigurationException` if you attempt to use HTTP.

### 2. Facilitator Requirement

**A facilitator is REQUIRED in production.** You cannot perform cryptographic verification locally.

```php
// ✅ CORRECT
$facilitator = FacilitatorClient::coinbase('your-api-key');
$paymentHandler = new PaymentHandler(facilitator: $facilitator);

// ❌ WRONG - Will throw RuntimeException in production
$paymentHandler = new PaymentHandler(facilitator: null);
```

Set `APP_ENV=development` only for local testing.

### 3. API Key Security

**Never commit API keys to version control.**

```env
# .env file (add to .gitignore)
X402_FACILITATOR_API_KEY=your-secret-api-key
X402_FACILITATOR_URL=https://facilitator.coinbase.com/api/v1
```

```php
// Load from environment
$config = X402Config::fromEnvironment();
```

**Use secrets management in production:**
- AWS Secrets Manager
- HashiCorp Vault
- Kubernetes Secrets
- Azure Key Vault

## 🛡️ Replay Attack Prevention

### Nonce Tracking (Required for Production)

Implement nonce tracking to prevent replay attacks where an attacker resubmits a valid payment:

```php
use X402\Nonce\RedisNonceTracker;

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('your-redis-password'); // If using authentication

$nonceTracker = new RedisNonceTracker($redis, 'x402:nonces:');

$paymentHandler = new PaymentHandler(
    facilitator: $facilitator,
    nonceTracker: $nonceTracker
);
```

**How it works:**
1. Each payment includes a unique nonce
2. The nonce is stored in Redis with TTL = validBefore timestamp
3. If the same nonce is submitted again, it's rejected
4. After validBefore expires, the nonce is automatically removed

**Redis Configuration:**
```redis
# Increase max memory for nonce storage
maxmemory 256mb
maxmemory-policy volatile-ttl

# Enable persistence for crash recovery
save 900 1
save 300 10
save 60 10000
```

## 🚦 Rate Limiting (Required for Production)

Prevent DoS attacks by limiting verification requests:

```php
use X402\RateLimit\RedisRateLimiter;

$rateLimiter = new RedisRateLimiter(
    redis: $redis,
    maxRequests: 100,      // Max requests per window
    windowSeconds: 60,      // Time window (1 minute)
    keyPrefix: 'x402:ratelimit:'
);

$paymentHandler = new PaymentHandler(
    facilitator: $facilitator,
    rateLimiter: $rateLimiter
);
```

**Recommended Limits by Use Case:**

| Use Case | Max Requests | Window | Notes |
|----------|--------------|--------|-------|
| Public API | 100 | 60s | Standard public endpoint |
| High-value API | 10 | 60s | Premium content, stricter limits |
| AI/Bot traffic | 1000 | 60s | Expecting high automated traffic |
| Internal API | 500 | 60s | Internal services with known traffic |

**Rate Limiting Strategy:**
```php
// Rate limit by IP address
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimiter->setIdentifier($clientIp);

// Or rate limit by payer address (after verification)
$result = $paymentHandler->verifyPayment($paymentHeader, $requirements);
$rateLimiter->setIdentifier($result['from']);
```

## 🔍 Compliance Checks

Implement AML/KYC and sanctions screening:

```php
use X402\Compliance\ComplianceCheckInterface;

class SanctionsListChecker implements ComplianceCheckInterface
{
    private array $blockedAddresses;
    
    public function __construct()
    {
        // Load from database or external service
        $this->blockedAddresses = $this->loadBlockedAddresses();
    }
    
    public function check(string $address, string $network): bool
    {
        // Normalize address
        $address = strtolower($address);
        
        // Check against local blocklist
        if (in_array($address, $this->blockedAddresses, true)) {
            error_log("Blocked address attempted payment: {$address}");
            return false;
        }
        
        // Check against external sanctions API
        if ($this->checkExternalSanctionsList($address)) {
            error_log("Sanctioned address attempted payment: {$address}");
            return false;
        }
        
        return true;
    }
    
    private function checkExternalSanctionsList(string $address): bool
    {
        // Example: TRM Labs, Chainalysis, etc.
        // return $this->trmLabsClient->isAddressSanctioned($address);
        return false;
    }
    
    private function loadBlockedAddresses(): array
    {
        // Load from your database
        return [];
    }
}

$complianceCheck = new SanctionsListChecker();

$paymentHandler = new PaymentHandler(
    facilitator: $facilitator,
    complianceCheck: $complianceCheck
);
```

**Compliance Services:**
- [TRM Labs](https://www.trmlabs.com/) - Blockchain intelligence
- [Chainalysis](https://www.chainalysis.com/) - Compliance and investigation
- [Elliptic](https://www.elliptic.co/) - Crypto risk management

## 📊 Audit Logging

Log all payment attempts for compliance and debugging:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

$logger = new Logger('x402');

// File logging
$logger->pushHandler(new RotatingFileHandler(
    'logs/x402.log',
    30, // Keep 30 days
    Logger::INFO
));

// Add context
$logger->pushProcessor(new IntrospectionProcessor());
$logger->pushProcessor(new WebProcessor());

$paymentHandler = new PaymentHandler(
    facilitator: $facilitator,
    logger: $logger
);
```

**What to Log:**

✅ **Always log:**
- Payment verification attempts (success and failure)
- Payer addresses
- Payment amounts and resources
- Timestamps
- Client IP addresses
- Compliance check results

❌ **Never log:**
- Private keys
- API keys
- Full payment signatures (just hash them)
- Sensitive user data (PII)

**Example Log Entry:**
```json
{
  "timestamp": "2025-11-04T10:30:45Z",
  "level": "INFO",
  "message": "Payment verified successfully",
  "context": {
    "from": "0x1234...5678",
    "amount": "1000000",
    "network": "base-mainnet",
    "resource": "/api/v1/data",
    "ip": "203.0.113.42",
    "settlement_tx": "0xabcd...ef01"
  }
}
```

## 🔐 Input Validation

The library validates all inputs by default, but add custom validation for your use case:

```php
use X402\Validation\Validator;
use X402\Exceptions\ValidationException;

// Validate and sanitize URLs
$sanitizedUrl = Validator::sanitizeUrl($userProvidedUrl);

// Validate addresses
if (!Validator::isValidAddress($address, $network)) {
    throw new ValidationException("Invalid address format");
}

// Sanitize string inputs
$sanitizedDescription = Validator::sanitizeString($description, maxLength: 500);

// Check nonce uniqueness
$isUnique = Validator::isNonceUnique($nonce, function($nonce) use ($redis) {
    return !$redis->exists("nonce:{$nonce}");
});
```

## ⚙️ Configuration Best Practices

### Environment-Based Configuration

```php
// config/x402.php (Laravel example)
return [
    'facilitator_url' => env('X402_FACILITATOR_URL'),
    'facilitator_api_key' => env('X402_FACILITATOR_API_KEY'),
    'timeout' => env('X402_TIMEOUT', 30),
    'auto_settle' => env('X402_AUTO_SETTLE', true),
    'buffer_seconds' => env('X402_BUFFER_SECONDS', 6),
    'verify_ssl' => env('X402_VERIFY_SSL', true),
    
    // Your payment address
    'pay_to_address' => env('X402_PAY_TO_ADDRESS'),
    
    // Token contracts
    'usdc_address' => env('X402_USDC_ADDRESS', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'), // Base USDC
];
```

### Production .env

```env
APP_ENV=production
APP_DEBUG=false

X402_FACILITATOR_URL=https://facilitator.coinbase.com/api/v1
X402_FACILITATOR_API_KEY=your-production-api-key
X402_TIMEOUT=30
X402_AUTO_SETTLE=true
X402_BUFFER_SECONDS=6
X402_VERIFY_SSL=true

X402_PAY_TO_ADDRESS=0xYourProductionAddress
X402_USDC_ADDRESS=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
```

### Development .env

```env
APP_ENV=development
APP_DEBUG=true

X402_FACILITATOR_URL=http://localhost:3000
X402_TIMEOUT=30
X402_AUTO_SETTLE=true
X402_VERIFY_SSL=false

X402_PAY_TO_ADDRESS=0xYourTestAddress
X402_USDC_ADDRESS=0x036CbD53842c5426634e7929541eC2318f3dCF7e  # Base Sepolia USDC

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## 🚨 Error Handling

Don't expose internal errors to clients:

```php
try {
    $result = $paymentHandler->verifyPayment($paymentHeader, $requirements);
    
    // Success - return your content
    return ['data' => $yourContent];
    
} catch (PaymentRequiredException $e) {
    // Missing or invalid payment - return 402
    $response = new PaymentRequiredResponse(
        x402Version: 1,
        accepts: [$requirements],
        error: 'Payment required' // Generic message
    );
    $response->send();
    
} catch (ComplianceException $e) {
    // Compliance check failed
    $logger->warning('Compliance check failed', [
        'error' => $e->getMessage(),
        'address' => $paymentData['from'] ?? 'unknown'
    ]);
    
    http_response_code(403);
    echo json_encode(['error' => 'Payment not accepted']);
    
} catch (FacilitatorException $e) {
    // Facilitator error - log details but don't expose
    $logger->error('Facilitator error', ['error' => $e->getMessage()]);
    
    http_response_code(502);
    echo json_encode(['error' => 'Payment verification unavailable']);
    
} catch (\Throwable $e) {
    // Unexpected error - log and return generic message
    $logger->critical('Unexpected error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
```

## 📋 Production Deployment Checklist

### Before Deployment

- [ ] Set `APP_ENV=production`
- [ ] Use HTTPS for all facilitator URLs
- [ ] Configure Redis for nonce tracking
- [ ] Enable rate limiting
- [ ] Implement compliance checks (if required by regulations)
- [ ] Set up PSR-3 logging with rotation
- [ ] Configure proper error handling
- [ ] Test with testnet before mainnet
- [ ] Review and set appropriate timeouts
- [ ] Secure API keys in secrets manager
- [ ] Enable SSL verification (`verifySSL=true`)
- [ ] Set up monitoring and alerting

### Monitoring

**Metrics to Track:**
- Payment verification success rate
- Payment settlement success rate
- Average verification time
- Rate limit hits per hour
- Compliance check failures
- Facilitator errors
- Nonce collision attempts

**Alerts to Configure:**
- Success rate drops below 95%
- Verification time exceeds 5 seconds
- Rate limit exceeded (potential DoS)
- Multiple compliance failures
- Facilitator unavailable

### Infrastructure

**Recommended Setup:**
```
┌─────────────┐
│   Clients   │
└──────┬──────┘
       │
       │ HTTPS
       │
┌──────▼──────────┐
│  Load Balancer  │
└──────┬──────────┘
       │
   ┌───┴───┐
   │       │
┌──▼──┐ ┌──▼──┐
│ App │ │ App │  (Your PHP application)
└──┬──┘ └──┬──┘
   │       │
   └───┬───┘
       │
┌──────▼──────────┐
│     Redis       │  (Nonce tracking, rate limiting)
└─────────────────┘
       │
┌──────▼──────────┐
│   Facilitator   │  (Coinbase, PayAI, etc.)
└─────────────────┘
```

**Redis High Availability:**
- Use Redis Sentinel or Cluster for production
- Enable persistence (RDB + AOF)
- Regular backups
- Monitor memory usage

## 🔄 Incident Response

### If API Keys are Compromised

1. **Immediately rotate keys** in your facilitator dashboard
2. Update environment variables with new keys
3. Deploy to all instances
4. Review logs for unauthorized usage
5. Monitor for unusual activity

### If Redis is Compromised

1. Flush potentially compromised nonce data
2. Restart with new authentication
3. Review logs for replay attacks
4. Update Redis password
5. Verify network security

### If Payment Fraud is Detected

1. Block the address in compliance checks
2. Review transaction history
3. Contact facilitator support if needed
4. Update fraud detection rules
5. Document the incident

## 📚 Additional Resources

- [OWASP API Security Top 10](https://owasp.org/www-project-api-security/)
- [CWE-294: Authentication Bypass by Capture-replay](https://cwe.mitre.org/data/definitions/294.html)
- [x402 Security Considerations](https://docs.cdp.coinbase.com/x402/core-concepts/security)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)

## 🆘 Security Contact

If you discover a security vulnerability, please email: security@example.com

**Do NOT open public issues for security vulnerabilities.**

---

**Remember:** Security is not a one-time setup. Regularly review logs, update dependencies, and stay informed about new threats and best practices.
