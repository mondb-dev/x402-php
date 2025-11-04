<?php

declare(strict_types=1);

namespace X402\Config;

use X402\Exceptions\ConfigurationException;
use X402\Facilitator\FacilitatorClient;

/**
 * Configuration class for x402 library.
 * 
 * Provides centralized configuration management for facilitator URLs,
 * API keys, timeouts, and other settings.
 */
class X402Config
{
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_CONNECT_TIMEOUT = 5;
    private const DEFAULT_BUFFER_SECONDS = 6;
    private const DEFAULT_CLOCK_DRIFT_TOLERANCE = 30;
    
    /**
     * @param string|null $facilitatorUrl Base URL of the facilitator service
     * @param string|null $facilitatorApiKey API key for facilitator authentication
     * @param int $timeout HTTP request timeout in seconds
     * @param int $connectTimeout HTTP connection timeout in seconds
     * @param bool $autoSettle Whether to automatically settle payments
     * @param int $validBeforeBufferSeconds Buffer time for block confirmation delays
     * @param int $clockDriftToleranceSeconds Tolerance for clock drift between client/server
     * @param bool $verifySSL Whether to verify SSL certificates (should be true in production)
     * @param string|null $userAgent Custom user agent string
     * @param array<string, mixed> $httpOptions Additional HTTP client options
     */
    public function __construct(
        private ?string $facilitatorUrl = null,
        private ?string $facilitatorApiKey = null,
        private int $timeout = self::DEFAULT_TIMEOUT,
        private int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        private bool $autoSettle = true,
        private int $validBeforeBufferSeconds = self::DEFAULT_BUFFER_SECONDS,
        private int $clockDriftToleranceSeconds = self::DEFAULT_CLOCK_DRIFT_TOLERANCE,
        private bool $verifySSL = true,
        private ?string $userAgent = null,
        private array $httpOptions = []
    ) {
        $this->validate();
    }

    /**
     * Create configuration from environment variables.
     * 
     * Reads the following environment variables:
     * - X402_FACILITATOR_URL or FACILITATOR_URL (required)
     * - X402_FACILITATOR_API_KEY or FACILITATOR_API_KEY (optional)
     * - X402_TIMEOUT (optional, defaults to 30)
     * - X402_CONNECT_TIMEOUT (optional, defaults to 5)
     * - X402_AUTO_SETTLE (optional, defaults to true)
     * - X402_BUFFER_SECONDS (optional, defaults to 6)
     * - X402_VERIFY_SSL (optional, defaults to true)
     */
    public static function fromEnvironment(): self
    {
        $facilitatorUrl = self::getEnv('X402_FACILITATOR_URL') 
            ?? self::getEnv('FACILITATOR_URL');
        
        if ($facilitatorUrl === null) {
            throw new ConfigurationException(
                'Missing required environment variable: X402_FACILITATOR_URL or FACILITATOR_URL'
            );
        }

        $facilitatorApiKey = self::getEnv('X402_FACILITATOR_API_KEY') 
            ?? self::getEnv('FACILITATOR_API_KEY');

        $timeout = (int)(self::getEnv('X402_TIMEOUT') ?? self::DEFAULT_TIMEOUT);
        $connectTimeout = (int)(self::getEnv('X402_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT);
        $autoSettle = filter_var(
            self::getEnv('X402_AUTO_SETTLE') ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        );
        $bufferSeconds = (int)(self::getEnv('X402_BUFFER_SECONDS') ?? self::DEFAULT_BUFFER_SECONDS);
        $clockDrift = (int)(self::getEnv('X402_CLOCK_DRIFT_TOLERANCE') ?? self::DEFAULT_CLOCK_DRIFT_TOLERANCE);
        $verifySSL = filter_var(
            self::getEnv('X402_VERIFY_SSL') ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        );

        return new self(
            facilitatorUrl: $facilitatorUrl,
            facilitatorApiKey: $facilitatorApiKey,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            autoSettle: $autoSettle,
            validBeforeBufferSeconds: $bufferSeconds,
            clockDriftToleranceSeconds: $clockDrift,
            verifySSL: $verifySSL
        );
    }

    /**
     * Create configuration for Coinbase facilitator.
     */
    public static function coinbase(?string $apiKey = null): self
    {
        return new self(
            facilitatorUrl: 'https://facilitator.coinbase.com/api/v1',
            facilitatorApiKey: $apiKey,
            verifySSL: true
        );
    }

    /**
     * Create configuration for PayAI facilitator.
     */
    public static function payai(?string $apiKey = null): self
    {
        return new self(
            facilitatorUrl: 'https://facilitator.payai.network',
            facilitatorApiKey: $apiKey,
            verifySSL: true
        );
    }

    /**
     * Create configuration for local development.
     * 
     * @param string $url Local facilitator URL (default: http://localhost:3000)
     */
    public static function local(string $url = 'http://localhost:3000'): self
    {
        return new self(
            facilitatorUrl: $url,
            verifySSL: false,
            autoSettle: true
        );
    }

    /**
     * Get facilitator URL.
     */
    public function getFacilitatorUrl(): ?string
    {
        return $this->facilitatorUrl;
    }

    /**
     * Get facilitator API key.
     */
    public function getFacilitatorApiKey(): ?string
    {
        return $this->facilitatorApiKey;
    }

    /**
     * Get HTTP timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get HTTP connection timeout in seconds.
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * Check if auto-settle is enabled.
     */
    public function isAutoSettle(): bool
    {
        return $this->autoSettle;
    }

    /**
     * Get valid-before buffer seconds.
     */
    public function getValidBeforeBufferSeconds(): int
    {
        return $this->validBeforeBufferSeconds;
    }

    /**
     * Get clock drift tolerance in seconds.
     */
    public function getClockDriftToleranceSeconds(): int
    {
        return $this->clockDriftToleranceSeconds;
    }

    /**
     * Check if SSL verification is enabled.
     */
    public function isVerifySSL(): bool
    {
        return $this->verifySSL;
    }

    /**
     * Get user agent string.
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * Get additional HTTP options.
     */
    public function getHttpOptions(): array
    {
        return $this->httpOptions;
    }

    /**
     * Create a FacilitatorClient from this configuration.
     */
    public function createFacilitatorClient(): FacilitatorClient
    {
        if ($this->facilitatorUrl === null) {
            throw new ConfigurationException('Facilitator URL is required to create FacilitatorClient');
        }

        $options = array_merge([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'verify' => $this->verifySSL,
        ], $this->httpOptions);

        if ($this->userAgent !== null) {
            $options['headers']['User-Agent'] = $this->userAgent;
        }

        return new FacilitatorClient(
            $this->facilitatorUrl,
            $options,
            $this->facilitatorApiKey
        );
    }

    /**
     * Validate configuration values.
     * 
     * @throws ConfigurationException
     */
    private function validate(): void
    {
        // Validate facilitator URL if provided
        if ($this->facilitatorUrl !== null) {
            if (!filter_var($this->facilitatorUrl, FILTER_VALIDATE_URL)) {
                throw new ConfigurationException('Invalid facilitator URL format');
            }

            $scheme = parse_url($this->facilitatorUrl, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new ConfigurationException('Facilitator URL must use http or https scheme');
            }

            // In production, enforce HTTPS
            if ($scheme !== 'https' && $this->isProduction()) {
                throw new ConfigurationException(
                    'SECURITY: Facilitator URL must use HTTPS in production. ' .
                    'Set APP_ENV=development for testing with HTTP.'
                );
            }
        }

        // Validate timeouts
        if ($this->timeout <= 0 || $this->timeout > 300) {
            throw new ConfigurationException('Timeout must be between 1 and 300 seconds');
        }

        if ($this->connectTimeout <= 0 || $this->connectTimeout > 60) {
            throw new ConfigurationException('Connect timeout must be between 1 and 60 seconds');
        }

        // Validate buffer seconds
        if ($this->validBeforeBufferSeconds < 0 || $this->validBeforeBufferSeconds > 300) {
            throw new ConfigurationException('Valid before buffer must be between 0 and 300 seconds');
        }

        // Validate clock drift tolerance
        if ($this->clockDriftToleranceSeconds < 0 || $this->clockDriftToleranceSeconds > 300) {
            throw new ConfigurationException('Clock drift tolerance must be between 0 and 300 seconds');
        }

        // Warn if SSL verification is disabled in production
        if (!$this->verifySSL && $this->isProduction()) {
            trigger_error(
                'WARNING: SSL verification is disabled in production. This is a security risk.',
                E_USER_WARNING
            );
        }
    }

    /**
     * Check if running in production environment.
     */
    private function isProduction(): bool
    {
        $env = strtolower(self::getEnv('APP_ENV') ?? self::getEnv('ENVIRONMENT') ?? 'production');
        return !in_array($env, ['development', 'dev', 'local', 'test', 'testing'], true);
    }

    /**
     * Get environment variable with fallback to null.
     */
    private static function getEnv(string $key): ?string
    {
        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : null;
    }

    /**
     * Set facilitator URL (for testing).
     */
    public function setFacilitatorUrl(?string $url): self
    {
        $this->facilitatorUrl = $url;
        $this->validate();
        return $this;
    }

    /**
     * Set facilitator API key (for testing).
     */
    public function setFacilitatorApiKey(?string $apiKey): self
    {
        $this->facilitatorApiKey = $apiKey;
        return $this;
    }

    /**
     * Disable SSL verification (for local development only).
     */
    public function disableSSLVerification(): self
    {
        if ($this->isProduction()) {
            throw new ConfigurationException(
                'Cannot disable SSL verification in production. Set APP_ENV=development for testing.'
            );
        }
        $this->verifySSL = false;
        return $this;
    }
}
