<?php

declare(strict_types=1);

namespace X402\Exceptions;

/**
 * Exception thrown when network/HTTP operations fail.
 */
class NetworkException extends X402Exception
{
    /**
     * Create exception for timeout.
     */
    public static function timeout(string $operation, float $timeout): self
    {
        return new self("Network timeout after {$timeout}s during: {$operation}");
    }

    /**
     * Create exception for connection failure.
     */
    public static function connectionFailed(string $url, string $reason): self
    {
        return new self("Failed to connect to {$url}: {$reason}");
    }

    /**
     * Create exception for general network error.
     */
    public static function error(string $message): self
    {
        return new self($message);
    }
}

