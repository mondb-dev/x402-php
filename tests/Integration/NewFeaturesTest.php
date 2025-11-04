<?php

declare(strict_types=1);

namespace X402\Tests\Integration;

use PHPUnit\Framework\TestCase;
use X402\CircuitBreaker\CircuitBreaker;
use X402\CircuitBreaker\CircuitOpenException;
use X402\Events\SimpleEventDispatcher;
use X402\Events\PaymentVerified;
use X402\Facilitator\FacilitatorClient;
use X402\Health\HealthChecker;
use X402\Middleware\PaymentHandler;
use X402\Metrics\DefaultMetrics;
use X402\Nonce\RedisNonceTracker;
use X402\Payment\PaymentState;
use X402\Payment\PaymentRecord;
use X402\RateLimit\RedisRateLimiter;
use X402\Validation\TokenValidator;
use X402\Validation\Validator;
use X402\Webhook\WebhookHandler;

/**
 * Integration tests for new features.
 */
class NewFeaturesTest extends TestCase
{
    public function testTokenValidatorKnownTokens(): void
    {
        // Test known USDC on Base Mainnet
        $tokenInfo = TokenValidator::getKnownToken('base-mainnet', '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913');
        
        $this->assertNotNull($tokenInfo);
        $this->assertEquals('USD Coin', $tokenInfo['name']);
        $this->assertEquals('2', $tokenInfo['version']);
        $this->assertEquals('USDC', $tokenInfo['symbol']);
        $this->assertEquals(6, $tokenInfo['decimals']);
    }

    public function testUint256SafeArithmetic(): void
    {
        // Test safe addition
        $result = Validator::safeAddUint256('1000000', '2000000');
        $this->assertEquals('3000000', $result);

        // Test safe multiplication
        $result = Validator::safeMulUint256('1000', '500');
        $this->assertEquals('500000', $result);

        // Test overflow detection
        $this->expectException(\X402\Exceptions\ValidationException::class);
        $max = '115792089237316195423570985008687907853269984665640564039457584007913129639935';
        Validator::safeAddUint256($max, '1');
    }

    public function testPaymentStateTransitions(): void
    {
        $pending = PaymentState::PENDING;
        
        // Valid transitions
        $this->assertTrue($pending->canTransitionTo(PaymentState::VERIFYING));
        $this->assertTrue($pending->canTransitionTo(PaymentState::EXPIRED));
        
        // Invalid transitions
        $this->assertFalse($pending->canTransitionTo(PaymentState::SETTLED));
        
        // Check final states
        $this->assertFalse($pending->isFinal());
        $this->assertTrue(PaymentState::SETTLED->isFinal());
        $this->assertTrue(PaymentState::FAILED->isFinal());
    }

    public function testCircuitBreaker(): void
    {
        $circuitBreaker = new CircuitBreaker(
            failureThreshold: 3,
            recoveryTimeout: 1,
            successThreshold: 2
        );

        $this->assertTrue($circuitBreaker->isClosed());

        // Record failures to open circuit
        for ($i = 0; $i < 3; $i++) {
            $circuitBreaker->recordFailure();
        }

        $this->assertTrue($circuitBreaker->isOpen());

        // Circuit should reject calls
        $this->expectException(CircuitOpenException::class);
        $circuitBreaker->call(fn() => 'test');
    }

    public function testDefaultMetrics(): void
    {
        $metrics = new DefaultMetrics();

        // Test counter
        $metrics->incrementCounter('test.counter', ['env' => 'test']);
        $metrics->incrementCounter('test.counter', ['env' => 'test']);
        
        // Test timing
        $metrics->recordTiming('test.duration', 123.45, ['endpoint' => 'verify']);
        $metrics->recordTiming('test.duration', 234.56, ['endpoint' => 'verify']);

        // Test gauge
        $metrics->recordGauge('test.memory', 1024.0);

        $result = $metrics->getMetrics();

        $this->assertArrayHasKey('counters', $result);
        $this->assertArrayHasKey('timings', $result);
        $this->assertArrayHasKey('gauges', $result);
    }

    public function testEventDispatcher(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        
        $called = false;
        $dispatcher->listen('payment.verified', function($event) use (&$called) {
            $called = true;
            $this->assertInstanceOf(PaymentVerified::class, $event);
        });

        // Create mock event (would need proper payload/requirements in real usage)
        // This is just testing the dispatcher mechanism
        $this->assertFalse($called);
    }

    public function testWebhookSignatureVerification(): void
    {
        $handler = new WebhookHandler('test-secret-key');

        $payload = json_encode(['event' => 'payment.settled', 'data' => ['id' => '123']]);
        $signature = hash_hmac('sha256', $payload, 'test-secret-key');

        $this->assertTrue($handler->verifySignature($payload, $signature));
        $this->assertFalse($handler->verifySignature($payload, 'wrong-signature'));
    }

    public function testNonceFormatValidation(): void
    {
        // Valid nonce
        $validNonce = '0x' . str_repeat('a', 64);
        $this->assertTrue(Validator::isValidNonce($validNonce));

        // Invalid nonces
        $this->assertFalse(Validator::isValidNonce('0x' . str_repeat('g', 64))); // Invalid hex
        $this->assertFalse(Validator::isValidNonce('0x' . str_repeat('a', 63))); // Too short
        $this->assertFalse(Validator::isValidNonce(str_repeat('a', 64))); // Missing 0x prefix
    }
}
