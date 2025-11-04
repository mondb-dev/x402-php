<?php

declare(strict_types=1);

namespace X402\Payment;

/**
 * Payment lifecycle states.
 * 
 * Represents the various states a payment can be in throughout its lifecycle.
 */
enum PaymentState: string
{
    /** Payment requirements created, awaiting payment */
    case PENDING = 'pending';
    
    /** Payment received, verification in progress */
    case VERIFYING = 'verifying';
    
    /** Payment verified successfully */
    case VERIFIED = 'verified';
    
    /** Settlement in progress */
    case SETTLING = 'settling';
    
    /** Payment settled successfully */
    case SETTLED = 'settled';
    
    /** Payment failed verification or settlement */
    case FAILED = 'failed';
    
    /** Payment authorization expired */
    case EXPIRED = 'expired';
    
    /** Payment cancelled */
    case CANCELLED = 'cancelled';

    /**
     * Check if this is a final state (no further transitions possible).
     *
     * @return bool True if final state
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::SETTLED,
            self::FAILED,
            self::EXPIRED,
            self::CANCELLED,
        ], true);
    }

    /**
     * Check if this is a successful state.
     *
     * @return bool True if successful
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::VERIFIED,
            self::SETTLED,
        ], true);
    }

    /**
     * Get valid transitions from this state.
     *
     * @return array<PaymentState>
     */
    public function validTransitions(): array
    {
        return match($this) {
            self::PENDING => [self::VERIFYING, self::EXPIRED, self::CANCELLED],
            self::VERIFYING => [self::VERIFIED, self::FAILED, self::EXPIRED],
            self::VERIFIED => [self::SETTLING, self::EXPIRED],
            self::SETTLING => [self::SETTLED, self::FAILED],
            self::SETTLED, self::FAILED, self::EXPIRED, self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to target state is valid.
     *
     * @param PaymentState $target Target state
     * @return bool True if transition is valid
     */
    public function canTransitionTo(PaymentState $target): bool
    {
        return in_array($target, $this->validTransitions(), true);
    }
}
