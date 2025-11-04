<?php

declare(strict_types=1);

namespace X402\Events;

/**
 * Event fired when payment is successfully verified.
 */
class PaymentVerified extends PaymentEvent
{
    public function getEventName(): string
    {
        return 'payment.verified';
    }
}
