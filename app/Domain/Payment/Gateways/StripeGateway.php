<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\PaymentIntent;
use App\Domain\Payment\WebhookEvent;
use App\Models\Order;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly string $webhookSecret,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function createIntent(Order $order): PaymentIntent
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $order->total_cents,
            'currency' => strtolower($order->currency),
            'metadata' => ['order_id' => $order->id, 'reference' => $order->reference],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return new PaymentIntent(
            id: $intent->id,
            clientSecret: $intent->client_secret,
            amountCents: $intent->amount,
            currency: strtoupper($intent->currency),
            gateway: $this->name(),
            raw: $intent->toArray(),
        );
    }

    public function parseWebhook(string $payload, array $headers): WebhookEvent
    {
        $sig = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? '';

        try {
            $event = Webhook::constructEvent($payload, $sig, $this->webhookSecret);
        } catch (UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) {
            throw new \RuntimeException('Invalid Stripe webhook signature: ' . $e->getMessage());
        }

        $type = match ($event->type) {
            'payment_intent.succeeded' => WebhookEvent::TYPE_SUCCEEDED,
            'payment_intent.payment_failed' => WebhookEvent::TYPE_FAILED,
            'charge.refunded' => WebhookEvent::TYPE_REFUNDED,
            default => $event->type,
        };

        return new WebhookEvent(
            id: $event->id,
            type: $type,
            intentId: $event->data->object->id ?? '',
            raw: $event->toArray(),
        );
    }

    public function refund(string $intentId, ?int $amountCents = null): bool
    {
        $params = ['payment_intent' => $intentId];
        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }
        $refund = $this->stripe->refunds->create($params);
        return $refund->status === 'succeeded';
    }
}
