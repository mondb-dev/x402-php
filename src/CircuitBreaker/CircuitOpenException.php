<?php

declare(strict_types=1);

namespace X402\CircuitBreaker;

/**
 * Circuit breaker exception thrown when circuit is open.
 */
class CircuitOpenException extends \RuntimeException
{
    public function __construct(string $message = 'Circuit breaker is open', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
