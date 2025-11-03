<?php

/**
 * Production-Ready x402 Payment Handler Example
 * 
 * This example demonstrates how to configure x402-php with all security features enabled
 * for production deployment.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Redis;
use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Nonce\RedisNonceTracker;
use X402\RateLimit\RedisRateLimiter;

// ============================================================================
// 1. Configure Redis (for nonce tracking and rate limiting)
// ============================================================================

$redis = new Redis();
$redis->connect(
    getenv('REDIS_HOST') ?: '127.0.0.1',
    (int)(getenv('REDIS_PORT') ?: 6379)
);

// Optional: Authenticate with password
if ($password = getenv('REDIS_PASSWORD')) {
    $redis->auth($password);
}

// Optional: Select database
$redis->select((int)(getenv('REDIS_DB') ?: 0));

// ============================================================================
// 2. Configure Facilitator (REQUIRED for production)
// ============================================================================

// Option A: Use PayAI Facilitator (default, recommended)
$facilitator = FacilitatorClient::payai(
    apiKey: getenv('FACILITATOR_API_KEY'),
    timeout: 30
);

// Option B: Use Coinbase Facilitator
// $facilitator = FacilitatorClient::coinbase(
//     apiKey: getenv('FACILITATOR_API_KEY'),
//     timeout: 30
// );

// Option C: Use self-hosted facilitator
// $facilitator = FacilitatorClient::selfHosted(
//     baseUrl: getenv('FACILITATOR_BASE_URL'),
//     apiKey: getenv('FACILITATOR_API_KEY')
// );

// ============================================================================
// 3. Configure Nonce Tracker (REQUIRED - prevents replay attacks)
// ============================================================================

$nonceTracker = new RedisNonceTracker(
    redis: $redis,
    namespace: 'myapp_production' // Isolate nonces per application/environment
);

// ============================================================================
// 4. Configure Rate Limiter (HIGHLY RECOMMENDED - prevents DoS)
// ============================================================================

$rateLimiter = new RedisRateLimiter(
    redis: $redis,
    maxAttempts: (int)(getenv('RATE_LIMIT_MAX_ATTEMPTS') ?: 10),
    decaySeconds: (int)(getenv('RATE_LIMIT_DECAY_SECONDS') ?: 60),
    namespace: 'myapp_production'
);

// ============================================================================
// 5. Configure Logger (RECOMMENDED - for audit trails)
// ============================================================================

$logger = new Logger('x402');

// Rotate logs daily, keep 30 days of history
$logger->pushHandler(
    new RotatingFileHandler(
        filename: getenv('LOG_PATH') ?: '/var/log/x402/payments.log',
        maxFiles: 30,
        level: Logger::INFO
    )
);

// ============================================================================
// 6. Optional: Configure Compliance Checks (for AML/KYC)
// ============================================================================

// Uncomment and implement if you need compliance screening
// use X402\Compliance\ComplianceCheckInterface;
// use X402\Compliance\ComplianceResult;
//
// class MyComplianceCheck implements ComplianceCheckInterface
// {
//     public function checkAddress(string $address, string $network): ComplianceResult
//     {
//         // Integrate with your compliance provider (Chainalysis, Elliptic, etc.)
//         $result = $this->provider->screenAddress($address);
//         
//         if ($result['is_sanctioned']) {
//             return new ComplianceResult(
//                 isBlocked: true,
//                 reason: 'Address is on sanctions list'
//             );
//         }
//         
//         return new ComplianceResult(isBlocked: false);
//     }
// }
//
// $complianceCheck = new MyComplianceCheck($yourProvider);

// ============================================================================
// 7. Optional: Configure Metrics (for monitoring)
// ============================================================================

// Uncomment and implement if you need metrics
// use X402\Metrics\MetricsInterface;
//
// class MyMetrics implements MetricsInterface
// {
//     public function incrementCounter(string $metric, array $tags = []): void
//     {
//         // Send to your metrics service (Prometheus, StatsD, etc.)
//     }
//     
//     public function recordTiming(string $metric, float $duration, array $tags = []): void
//     {
//         // Record timing metrics
//     }
//     
//     public function recordGauge(string $metric, float $value, array $tags = []): void
//     {
//         // Record gauge metrics
//     }
// }
//
// $metrics = new MyMetrics($yourMetricsService);

// ============================================================================
// 8. Create Payment Handler with all security features
// ============================================================================

$handler = new PaymentHandler(
    facilitator: $facilitator,
    autoSettle: true,
    validBeforeBufferSeconds: 6, // 6 seconds buffer for block confirmations
    nonceTracker: $nonceTracker,
    rateLimiter: $rateLimiter,
    complianceCheck: null, // Set to $complianceCheck if implemented
    metrics: null,         // Set to $metrics if implemented
    logger: $logger
);

// ============================================================================
// 9. Example: Process a payment request
// ============================================================================

try {
    // Create payment requirements
    $requirements = $handler->createPaymentRequirements(
        payTo: '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb', // Your receiving address
        amount: '1000000', // 1 USDC (6 decimals)
        resource: 'https://api.example.com/premium-content/123',
        description: 'Access to premium content',
        asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', // USDC on Base
        network: 'base-mainnet',
        scheme: 'exact',
        timeout: 300,
        mimeType: 'application/json',
        extra: [
            'name' => 'MyApp Payment',
            'version' => '1',
        ],
        id: uniqid('payment_', true) // Unique ID for this payment requirement
    );

    // Simulate incoming HTTP request headers
    $headers = [
        'X-Payment' => $_SERVER['HTTP_X_PAYMENT'] ?? null,
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ];

    // Process the payment
    $result = $handler->processPayment(
        headers: $headers,
        requirements: $requirements,
        identifier: $_SERVER['REMOTE_ADDR'] ?? null // For rate limiting
    );

    if ($result['verified']) {
        // Payment verified successfully!
        
        $logger->info('Payment processed successfully', [
            'payment_id' => $requirements->id,
            'network' => $result['payload']->network,
        ]);

        // Return success response
        http_response_code(200);
        header('Content-Type: application/json');
        
        // Include settlement data in response header if auto-settled
        if ($result['settlement'] !== null) {
            $settlementHeader = $handler->createPaymentResponseHeader($result['settlement']);
            header("{$handler->getPaymentResponseHeaderName()}: {$settlementHeader}");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified',
            'content' => 'Here is your premium content...',
        ]);
        
    } else {
        // No valid payment provided - return 402 Payment Required
        
        $paymentRequired = $handler->createPaymentRequiredResponse(
            requirements: $requirements,
            error: 'Payment required to access this resource'
        );

        // IMPORTANT: Use send() method to properly emit 402 response
        // This ensures headers are sent before status code (PHP quirk: WWW-Authenticate causes 401 if status set first)
        $paymentRequired->send();
        exit;
    }

} catch (\X402\Exceptions\PaymentRequiredException $e) {
    // Payment verification failed
    
    $logger->warning('Payment verification failed', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);

    // Return appropriate error response
    if ($e->getCode() === \X402\Exceptions\ErrorCodes::RATE_LIMIT_EXCEEDED) {
        // Rate limit: Set headers first, then status code
        header('Retry-After: 60');
        header('Content-Type: application/json');
        http_response_code(429);
    } else {
        // Payment required: Use send() method or set headers before status code
        $paymentRequired = $handler->createPaymentRequiredResponse(
            requirements: $requirements ?? $handler->createPaymentRequirements(
                payTo: '0x0000000000000000000000000000000000000000',
                amount: '0',
                resource: $_SERVER['REQUEST_URI'] ?? '/',
                description: 'Payment required',
                asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
                network: 'base-mainnet',
                extra: ['name' => 'USD Coin', 'version' => '2']
            ),
            error: $e->getMessage()
        );
        $paymentRequired->send();
        exit;
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);

} catch (\X402\Exceptions\ComplianceException $e) {
    // Compliance check failed (address is blocked)
    
    $logger->error('Compliance check failed', [
        'address' => $e->getAddress(),
        'reason' => $e->getMessage(),
    ]);

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Payment from this address is not allowed',
    ]);

} catch (\Exception $e) {
    // Unexpected error
    
    $logger->error('Unexpected error processing payment', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
    ]);
}

// ============================================================================
// 10. Health Check Endpoint (recommended)
// ============================================================================

// Add a health check to verify all dependencies are working
if (($_SERVER['REQUEST_URI'] ?? '') === '/health') {
    $health = [
        'status' => 'ok',
        'checks' => [],
    ];

    // Check Redis
    try {
        $redis->ping();
        $health['checks']['redis'] = 'ok';
    } catch (\Exception $e) {
        $health['status'] = 'error';
        $health['checks']['redis'] = 'error';
    }

    // Check Facilitator
    try {
        $supported = $facilitator->getSupported();
        $health['checks']['facilitator'] = 'ok';
    } catch (\Exception $e) {
        $health['status'] = 'degraded';
        $health['checks']['facilitator'] = 'error';
    }

    http_response_code($health['status'] === 'ok' ? 200 : 503);
    header('Content-Type: application/json');
    echo json_encode($health);
    exit;
}
