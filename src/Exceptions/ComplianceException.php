<?php

declare(strict_types=1);

namespace X402\Exceptions;

/**
 * Exception thrown when compliance check fails.
 */
class ComplianceException extends X402Exception
{
    private string $address;
    private array $metadata;

    public function __construct(
        string $message = 'Compliance check failed',
        string $address = '',
        array $metadata = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->address = $address;
        $this->metadata = $metadata;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
