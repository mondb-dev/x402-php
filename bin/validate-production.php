#!/usr/bin/env php
<?php

/**
 * Production Readiness Validator for x402-php
 * 
 * This script validates that your x402-php deployment meets all security requirements.
 * 
 * Usage:
 *   php bin/validate-production.php
 * 
 * Exit codes:
 *   0 - All checks passed
 *   1 - One or more checks failed
 */

declare(strict_types=1);

// Color codes for terminal output
const COLOR_GREEN = "\033[0;32m";
const COLOR_RED = "\033[0;31m";
const COLOR_YELLOW = "\033[1;33m";
const COLOR_RESET = "\033[0m";

class ProductionValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $passed = [];

    public function run(): int
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  x402-php Production Readiness Validator                  ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $this->checkEnvironment();
        $this->checkFacilitator();
        $this->checkNonceTracking();
        $this->checkRateLimiting();
        $this->checkLogging();
        $this->checkRedisExtension();
        $this->checkSecurityHeaders();
        $this->checkComposerDependencies();

        $this->printResults();

        return count($this->errors) > 0 ? 1 : 0;
    }

    private function checkEnvironment(): void
    {
        echo "🔍 Checking environment configuration...\n";

        $appEnv = getenv('APP_ENV') ?: getenv('ENVIRONMENT') ?: 'production';

        if (in_array(strtolower($appEnv), ['production', 'prod'], true)) {
            $this->passed[] = "APP_ENV is set to production";
        } else {
            $this->warnings[] = "APP_ENV is '{$appEnv}' (not production)";
        }

        if (getenv('FACILITATOR_BASE_URL')) {
            $this->passed[] = "FACILITATOR_BASE_URL is configured";
        } else {
            $this->errors[] = "FACILITATOR_BASE_URL environment variable is not set";
        }
    }

    private function checkFacilitator(): void
    {
        echo "🔍 Checking facilitator configuration...\n";

        $baseUrl = getenv('FACILITATOR_BASE_URL');
        
        if ($baseUrl) {
            if (str_starts_with($baseUrl, 'https://')) {
                $this->passed[] = "Facilitator URL uses HTTPS";
            } else {
                $this->errors[] = "Facilitator URL must use HTTPS (found: {$baseUrl})";
            }

            // Check if default facilitator is used
            if ($baseUrl === 'https://facilitator.payai.network') {
                $this->passed[] = "Using default PayAI facilitator";
            } elseif ($baseUrl === 'https://facilitator.coinbase.com/api/v1') {
                $this->passed[] = "Using Coinbase facilitator";
            } else {
                $this->warnings[] = "Using custom facilitator: {$baseUrl}";
            }
        }

        $apiKey = getenv('FACILITATOR_API_KEY');
        if ($apiKey && $apiKey !== '') {
            $this->passed[] = "FACILITATOR_API_KEY is configured";
        } else {
            $this->warnings[] = "FACILITATOR_API_KEY not set (may be required by your facilitator)";
        }
    }

    private function checkNonceTracking(): void
    {
        echo "🔍 Checking nonce tracking (replay attack prevention)...\n";

        if (getenv('REDIS_HOST') || getenv('REDIS_URL')) {
            $this->passed[] = "Redis configuration found for nonce tracking";
        } else {
            $this->errors[] = "REDIS_HOST or REDIS_URL not configured - nonce tracking disabled (CRITICAL SECURITY RISK!)";
            $this->errors[] = "Without nonce tracking, replay attacks are possible";
        }
    }

    private function checkRateLimiting(): void
    {
        echo "🔍 Checking rate limiting configuration...\n";

        if (getenv('RATE_LIMIT_ENABLED') === 'true') {
            $this->passed[] = "Rate limiting is enabled";

            $maxAttempts = getenv('RATE_LIMIT_MAX_ATTEMPTS');
            if ($maxAttempts && is_numeric($maxAttempts)) {
                $this->passed[] = "Rate limit: {$maxAttempts} attempts per window";
            } else {
                $this->warnings[] = "RATE_LIMIT_MAX_ATTEMPTS not configured (using default)";
            }
        } else {
            $this->warnings[] = "Rate limiting not explicitly enabled (DoS risk)";
            $this->warnings[] = "Set RATE_LIMIT_ENABLED=true to enable";
        }
    }

    private function checkLogging(): void
    {
        echo "🔍 Checking logging configuration...\n";

        if (getenv('LOG_LEVEL')) {
            $this->passed[] = "LOG_LEVEL is configured: " . getenv('LOG_LEVEL');
        } else {
            $this->warnings[] = "LOG_LEVEL not set (audit trail may be incomplete)";
        }

        if (getenv('LOG_CHANNEL') || getenv('LOG_PATH')) {
            $this->passed[] = "Logging output is configured";
        } else {
            $this->warnings[] = "LOG_CHANNEL/LOG_PATH not configured (logs may go to stdout only)";
        }
    }

    private function checkRedisExtension(): void
    {
        echo "🔍 Checking PHP extensions...\n";

        if (extension_loaded('redis')) {
            $this->passed[] = "Redis extension is installed";
        } else {
            $this->errors[] = "Redis extension not installed (required for NonceTracker and RateLimiter)";
            $this->errors[] = "Install with: pecl install redis";
        }

        if (extension_loaded('json')) {
            $this->passed[] = "JSON extension is installed";
        } else {
            $this->errors[] = "JSON extension not installed";
        }
    }

    private function checkSecurityHeaders(): void
    {
        echo "🔍 Checking security best practices...\n";

        if (ini_get('display_errors') === '0' || ini_get('display_errors') === '') {
            $this->passed[] = "display_errors is disabled";
        } else {
            $this->warnings[] = "display_errors is enabled (may leak sensitive information)";
        }

        if (ini_get('expose_php') === '0' || ini_get('expose_php') === '') {
            $this->passed[] = "expose_php is disabled";
        } else {
            $this->warnings[] = "expose_php is enabled (reveals PHP version)";
        }
    }

    private function checkComposerDependencies(): void
    {
        echo "🔍 Checking composer dependencies...\n";

        $composerLockPath = __DIR__ . '/../composer.lock';
        
        if (file_exists($composerLockPath)) {
            $this->passed[] = "composer.lock exists (dependencies are locked)";
            
            // Check if dependencies are up to date
            $composerJsonPath = __DIR__ . '/../composer.json';
            if (file_exists($composerJsonPath)) {
                $lockMtime = filemtime($composerLockPath);
                $jsonMtime = filemtime($composerJsonPath);
                
                if ($lockMtime >= $jsonMtime) {
                    $this->passed[] = "Dependencies are up to date";
                } else {
                    $this->warnings[] = "composer.json is newer than composer.lock (run 'composer update')";
                }
            }
        } else {
            $this->errors[] = "composer.lock not found (run 'composer install')";
        }

        // Check for vendor directory
        $vendorPath = __DIR__ . '/../vendor';
        if (is_dir($vendorPath)) {
            $this->passed[] = "vendor/ directory exists";
        } else {
            $this->errors[] = "vendor/ directory not found (run 'composer install')";
        }
    }

    private function printResults(): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "                    VALIDATION RESULTS                     \n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "\n";

        if (count($this->passed) > 0) {
            echo COLOR_GREEN . "✓ PASSED (" . count($this->passed) . ")" . COLOR_RESET . "\n";
            foreach ($this->passed as $pass) {
                echo "  ✓ {$pass}\n";
            }
            echo "\n";
        }

        if (count($this->warnings) > 0) {
            echo COLOR_YELLOW . "⚠ WARNINGS (" . count($this->warnings) . ")" . COLOR_RESET . "\n";
            foreach ($this->warnings as $warning) {
                echo "  ⚠ {$warning}\n";
            }
            echo "\n";
        }

        if (count($this->errors) > 0) {
            echo COLOR_RED . "✗ ERRORS (" . count($this->errors) . ")" . COLOR_RESET . "\n";
            foreach ($this->errors as $error) {
                echo "  ✗ {$error}\n";
            }
            echo "\n";
        }

        echo "═══════════════════════════════════════════════════════════\n";

        if (count($this->errors) === 0 && count($this->warnings) === 0) {
            echo COLOR_GREEN . "✓ All checks passed! Your deployment is production-ready." . COLOR_RESET . "\n";
        } elseif (count($this->errors) === 0) {
            echo COLOR_YELLOW . "⚠ Production ready with warnings. Review warnings above." . COLOR_RESET . "\n";
        } else {
            echo COLOR_RED . "✗ DEPLOYMENT NOT PRODUCTION READY!" . COLOR_RESET . "\n";
            echo "  Fix all errors before deploying to production.\n";
        }

        echo "\n";
        echo "For more information, see SECURITY_CHECKLIST.md\n";
        echo "\n";
    }
}

// Run validator
$validator = new ProductionValidator();
exit($validator->run());
