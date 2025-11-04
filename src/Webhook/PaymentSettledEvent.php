<?php

declare(strict_types=1);

namespace X402\Webhook;

/**
 * Event fired when payment is settled (webhook notification).
 */
class PaymentSettledEvent extends WebhookEvent
{
    public function getType(): string
    {
        return 'payment.settled';
    }

    public function getTransactionHash(): ?string
    {
        return $this->data['transactionHash'] ?? $this->data['transaction_hash'] ?? null;
    }

    public function getPaymentId(): ?string
    {
        return $this->data['paymentId'] ?? $this->data['payment_id'] ?? null;
    }
}
