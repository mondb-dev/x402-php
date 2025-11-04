<?php

declare(strict_types=1);

namespace X402\Webhook;

use X402\Exceptions\ValidationException;

/**
 * Base class for webhook events.
 */
abstract class WebhookEvent
{
    public function __construct(
        public readonly array $data,
        public readonly \DateTimeImmutable $timestamp
    ) {}

    /**
     * Get event type.
     *
     * @return string Event type
     */
    abstract public function getType(): string;

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => $this->getType(),
            'data' => $this->data,
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ISO8601),
        ];
    }
}
