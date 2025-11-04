<?php

declare(strict_types=1);

namespace X402\Events;

use X402\Types\PaymentPayload;
use X402\Types\PaymentRequirements;

/**
 * Base class for payment events.
 */
abstract class PaymentEvent
{
    public function __construct(
        public readonly PaymentPayload $payload,
        public readonly PaymentRequirements $requirements,
        public readonly \DateTimeImmutable $timestamp,
        public readonly array $metadata = []
    ) {}

    /**
     * Get event name.
     *
     * @return string Event name
     */
    abstract public function getEventName(): string;

    /**
     * Convert event to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => $this->getEventName(),
            'payload' => $this->payload->toArray(),
            'requirements' => $this->requirements->toArray(),
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ISO8601),
            'metadata' => $this->metadata,
        ];
    }
}
