<?php

declare(strict_types=1);

namespace X402\Events;

/**
 * Event fired when payment is successfully settled.
 */
class PaymentSettled extends PaymentEvent
{
    public function __construct(
        \X402\Types\PaymentPayload $payload,
        \X402\Types\PaymentRequirements $requirements,
        \DateTimeImmutable $timestamp,
        public readonly string $transactionHash,
        array $metadata = []
    ) {
        parent::__construct($payload, $requirements, $timestamp, $metadata);
    }

    public function getEventName(): string
    {
        return 'payment.settled';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'transactionHash' => $this->transactionHash,
        ]);
    }
}
