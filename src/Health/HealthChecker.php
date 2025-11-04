<?php

declare(strict_types=1);

namespace X402\Health;

use X402\Facilitator\FacilitatorClient;
use X402\Nonce\NonceTrackerInterface;
use X402\RateLimit\RateLimiterInterface;

/**
 * Health checker for x402 components.
 */
class HealthChecker
{
    public function __construct(
        private readonly ?FacilitatorClient $facilitator = null,
        private readonly ?\Redis $redis = null,
        private readonly ?NonceTrackerInterface $nonceTracker = null,
        private readonly ?RateLimiterInterface $rateLimiter = null
    ) {}

    /**
     * Perform health check on all components.
     *
     * @return HealthStatus Overall health status
     */
    public function check(): HealthStatus
    {
        $checks = [];

        // Check facilitator
        if ($this->facilitator !== null) {
            $checks['facilitator'] = $this->checkFacilitator();
        }

        // Check Redis
        if ($this->redis !== null) {
            $checks['redis'] = $this->checkRedis();
        }

        // Check nonce tracker
        if ($this->nonceTracker !== null) {
            $checks['nonce_tracker'] = $this->checkNonceTracker();
        }

        // Check rate limiter
        if ($this->rateLimiter !== null) {
            $checks['rate_limiter'] = $this->checkRateLimiter();
        }

        // PHP version check
        $checks['php'] = $this->checkPhp();

        // Required extensions
        $checks['extensions'] = $this->checkExtensions();

        // Determine overall health
        $healthy = !empty($checks) && !in_array(false, array_column($checks, 'healthy'), true);

        return new HealthStatus($healthy, $checks, new \DateTimeImmutable());
    }

    /**
     * Check facilitator connectivity.
     *
     * @return array<string, mixed>
     */
    private function checkFacilitator(): array
    {
        try {
            $config = $this->facilitator->getSupported();
            return [
                'healthy' => true,
                'schemes' => count($config->schemes),
                'networks' => count($config->networks),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connectivity.
     *
     * @return array<string, mixed>
     */
    private function checkRedis(): array
    {
        try {
            $this->redis->ping();
            return [
                'healthy' => true,
                'connected' => $this->redis->isConnected(),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check nonce tracker.
     *
     * @return array<string, mixed>
     */
    private function checkNonceTracker(): array
    {
        try {
            // Test nonce tracker with a test nonce
            $testNonce = '0x' . str_repeat('0', 64);
            $exists = $this->nonceTracker->hasNonce($testNonce);
            
            return [
                'healthy' => true,
                'operational' => true,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check rate limiter.
     *
     * @return array<string, mixed>
     */
    private function checkRateLimiter(): array
    {
        try {
            // Test rate limiter with a test identifier
            $testId = 'health-check-' . time();
            $allowed = $this->rateLimiter->isAllowed($testId);
            
            return [
                'healthy' => true,
                'operational' => true,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check PHP version.
     *
     * @return array<string, mixed>
     */
    private function checkPhp(): array
    {
        $version = PHP_VERSION;
        $healthy = version_compare($version, '8.1.0', '>=');

        return [
            'healthy' => $healthy,
            'version' => $version,
            'minimum' => '8.1.0',
        ];
    }

    /**
     * Check required PHP extensions.
     *
     * @return array<string, mixed>
     */
    private function checkExtensions(): array
    {
        $required = ['json', 'curl'];
        $recommended = ['redis', 'bcmath'];
        
        $missing = [];
        $missingRecommended = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        foreach ($recommended as $ext) {
            if (!extension_loaded($ext)) {
                $missingRecommended[] = $ext;
            }
        }

        return [
            'healthy' => empty($missing),
            'loaded' => get_loaded_extensions(),
            'missing_required' => $missing,
            'missing_recommended' => $missingRecommended,
        ];
    }
}
