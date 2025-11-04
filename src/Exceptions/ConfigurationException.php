<?php

declare(strict_types=1);

namespace X402\Exceptions;

/**
 * Exception thrown when configuration is invalid or missing.
 */
class ConfigurationException extends X402Exception
{
    /**
     * Create exception for missing configuration.
     */
    public static function missing(string $key): self
    {
        return new self("Missing required configuration: {$key}");
    }

    /**
     * Create exception for invalid configuration value.
     */
    public static function invalid(string $key, string $reason): self
    {
        return new self("Invalid configuration for {$key}: {$reason}");
    }

    /**
     * Create exception for environment setup issues.
     */
    public static function environment(string $message): self
    {
        return new self($message);
    }
}
