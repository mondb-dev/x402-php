<?php

declare(strict_types=1);

namespace X402\Webhook;

/**
 * Event fired when payment fails (webhook notification).
 */
class PaymentFailedEvent extends WebhookEvent
{
    public function getType(): string
    {
        return 'payment.failed';
    }

    public function getPaymentId(): ?string
    {
        return $this->data['paymentId'] ?? $this->data['payment_id'] ?? null;
    }

    public function getFailureReason(): ?string
    {
        return $this->data['reason'] ?? $this->data['error'] ?? null;
    }
}
