<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\PaymentIntent;
use App\Domain\Payment\WebhookEvent;
use App\Models\Order;
use Razorpay\Api\Api;

class RazorpayGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $webhookSecret,
    ) {}

    public function name(): string
    {
        return 'razorpay';
    }

    public function createIntent(Order $order): PaymentIntent
    {
        $api = new Api($this->key, $this->secret);
        $rpOrder = $api->order->create([
            'amount' => $order->total_cents,
            'currency' => $order->currency,
            'receipt' => $order->reference,
            'notes' => ['order_id' => $order->id],
        ]);

        return new PaymentIntent(
            id: $rpOrder['id'],
            clientSecret: $rpOrder['id'],
            amountCents: $order->total_cents,
            currency: $order->currency,
            gateway: $this->name(),
            raw: $rpOrder->toArray(),
        );
    }

    public function parseWebhook(string $payload, array $headers): WebhookEvent
    {
        $sig = $headers['x-razorpay-signature'][0] ?? $headers['X-Razorpay-Signature'][0] ?? '';
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('Invalid Razorpay webhook signature.');
        }

        $data = json_decode($payload, true);

        $type = match ($data['event'] ?? '') {
            'payment.captured', 'order.paid' => WebhookEvent::TYPE_SUCCEEDED,
            'payment.failed' => WebhookEvent::TYPE_FAILED,
            'refund.processed' => WebhookEvent::TYPE_REFUNDED,
            default => $data['event'] ?? 'unknown',
        };

        return new WebhookEvent(
            id: $data['payload']['payment']['entity']['id'] ?? uniqid('rp_'),
            type: $type,
            intentId: $data['payload']['payment']['entity']['order_id'] ?? '',
            raw: $data,
        );
    }

    public function refund(string $intentId, ?int $amountCents = null): bool
    {
        $api = new Api($this->key, $this->secret);
        $params = [];
        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }
        $api->payment->fetch($intentId)->refund($params);
        return true;
    }
}
