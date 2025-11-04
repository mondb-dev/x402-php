<?php

declare(strict_types=1);

namespace X402\Payment;

use X402\Types\PaymentPayload;
use X402\Types\PaymentRequirements;

/**
 * Payment record for tracking payment lifecycle.
 */
class PaymentRecord
{
    public function __construct(
        public readonly string $id,
        public readonly PaymentRequirements $requirements,
        public readonly ?PaymentPayload $payload,
        public readonly PaymentState $state,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt = null,
        public readonly ?string $transactionHash = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {}

    /**
     * Create a new payment record in PENDING state.
     *
     * @param string $id Payment ID
     * @param PaymentRequirements $requirements Payment requirements
     * @param array<string, mixed> $metadata Optional metadata
     * @return self
     */
    public static function createPending(
        string $id,
        PaymentRequirements $requirements,
        array $metadata = []
    ): self {
        return new self(
            id: $id,
            requirements: $requirements,
            payload: null,
            state: PaymentState::PENDING,
            createdAt: new \DateTimeImmutable(),
            metadata: $metadata
        );
    }

    /**
     * Transition to a new state.
     *
     * @param PaymentState $newState New state
     * @param PaymentPayload|null $payload Optional payment payload
     * @param string|null $transactionHash Optional transaction hash
     * @param string|null $errorMessage Optional error message
     * @return self New record with updated state
     * @throws \InvalidArgumentException If transition is invalid
     */
    public function transitionTo(
        PaymentState $newState,
        ?PaymentPayload $payload = null,
        ?string $transactionHash = null,
        ?string $errorMessage = null
    ): self {
        if (!$this->state->canTransitionTo($newState)) {
            throw new \InvalidArgumentException(
                "Invalid state transition from {$this->state->value} to {$newState->value}"
            );
        }

        return new self(
            id: $this->id,
            requirements: $this->requirements,
            payload: $payload ?? $this->payload,
            state: $newState,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
            transactionHash: $transactionHash ?? $this->transactionHash,
            errorMessage: $errorMessage,
            metadata: $this->metadata
        );
    }

    /**
     * Check if payment is in a final state.
     *
     * @return bool True if in final state
     */
    public function isFinal(): bool
    {
        return $this->state->isFinal();
    }

    /**
     * Check if payment was successful.
     *
     * @return bool True if successful
     */
    public function isSuccessful(): bool
    {
        return $this->state->isSuccessful();
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'requirements' => $this->requirements->toArray(),
            'payload' => $this->payload?->toArray(),
            'state' => $this->state->value,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ISO8601),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ISO8601),
            'transactionHash' => $this->transactionHash,
            'errorMessage' => $this->errorMessage,
            'metadata' => $this->metadata,
        ];
    }
}
