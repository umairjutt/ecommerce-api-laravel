<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\PaymentIntent;
use App\Domain\Payment\WebhookEvent;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

class PaypalGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $mode = 'sandbox',
    ) {}

    public function name(): string
    {
        return 'paypal';
    }

    public function createIntent(Order $order): PaymentIntent
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl() . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->reference,
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value' => number_format($order->total_cents / 100, 2, '.', ''),
                    ],
                ]],
            ])->throw()->json();

        return new PaymentIntent(
            id: $response['id'],
            clientSecret: $response['id'],
            amountCents: $order->total_cents,
            currency: $order->currency,
            gateway: $this->name(),
            raw: $response,
        );
    }

    public function parseWebhook(string $payload, array $headers): WebhookEvent
    {
        $data = json_decode($payload, true) ?? [];

        $type = match ($data['event_type'] ?? '') {
            'PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED' => WebhookEvent::TYPE_SUCCEEDED,
            'PAYMENT.CAPTURE.DENIED' => WebhookEvent::TYPE_FAILED,
            'PAYMENT.CAPTURE.REFUNDED' => WebhookEvent::TYPE_REFUNDED,
            default => $data['event_type'] ?? 'unknown',
        };

        return new WebhookEvent(
            id: $data['id'] ?? uniqid('pp_'),
            type: $type,
            intentId: $data['resource']['supplementary_data']['related_ids']['order_id']
                   ?? $data['resource']['id']
                   ?? '',
            raw: $data,
        );
    }

    public function refund(string $intentId, ?int $amountCents = null): bool
    {
        $token = $this->accessToken();
        $body = [];
        if ($amountCents !== null) {
            $body = ['amount' => ['value' => number_format($amountCents / 100, 2, '.', ''), 'currency_code' => 'USD']];
        }
        $r = Http::withToken($token)->post($this->baseUrl() . "/v2/payments/captures/{$intentId}/refund", $body);
        return $r->successful();
    }

    private function accessToken(): string
    {
        $r = Http::asForm()->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->baseUrl() . '/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json();
        return $r['access_token'];
    }

    private function baseUrl(): string
    {
        return $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
