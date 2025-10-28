<?php

declare(strict_types=1);

namespace X402\Exceptions;

use Throwable;

/**
 * Exception thrown when payment is required.
 */
class PaymentRequiredException extends X402Exception
{
    /**
     * @param string $message Error message
     * @param string|null $invalidReason Standardized error code from ErrorCodes
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = "",
        public readonly ?string $invalidReason = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
