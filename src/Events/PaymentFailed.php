<?php

declare(strict_types=1);

namespace X402\Events;

/**
 * Event fired when payment verification or settlement fails.
 */
class PaymentFailed extends PaymentEvent
{
    public function __construct(
        \X402\Types\PaymentPayload $payload,
        \X402\Types\PaymentRequirements $requirements,
        \DateTimeImmutable $timestamp,
        public readonly string $reason,
        public readonly ?\Throwable $exception = null,
        array $metadata = []
    ) {
        parent::__construct($payload, $requirements, $timestamp, $metadata);
    }

    public function getEventName(): string
    {
        return 'payment.failed';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'reason' => $this->reason,
            'exception' => $this->exception?->getMessage(),
        ]);
    }
}
