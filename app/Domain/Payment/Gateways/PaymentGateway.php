<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\PaymentIntent;
use App\Domain\Payment\WebhookEvent;
use App\Models\Order;

/**
 * Common interface for every payment gateway.
 *
 * Implement once per provider (Stripe, PayPal, Razorpay, ...). The rest of
 * the application talks to this interface only — no gateway-specific code
 * leaks into controllers or services.
 */
interface PaymentGateway
{
    public function name(): string;

    public function createIntent(Order $order): PaymentIntent;

    /**
     * Verify webhook signature and translate provider payload to our
     * canonical WebhookEvent. Throws if signature is invalid.
     */
    public function parseWebhook(string $payload, array $headers): WebhookEvent;

    public function refund(string $intentId, ?int $amountCents = null): bool;
}
