<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\OrderService;
use App\Domain\Payment\GatewayResolver;
use App\Domain\Payment\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly OrderService $orders,
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        try {
            $event = $this->gateways->get($gateway)
                ->parseWebhook($request->getContent(), $request->headers->all());
        } catch (\Throwable $e) {
            Log::warning("Webhook rejected ({$gateway}): " . $e->getMessage());
            return response()->json(['error' => 'invalid'], 400);
        }

        $idempotencyKey = "webhook:{$gateway}:{$event->id}";
        if (!Cache::add($idempotencyKey, 1, now()->addDay())) {
            return response()->json(['status' => 'duplicate']);
        }

        $order = Order::where('gateway', $gateway)
            ->where('gateway_intent_id', $event->intentId)
            ->first();

        if ($order === null) {
            Log::warning("Webhook for unknown intent {$event->intentId}");
            return response()->json(['status' => 'unknown_order']);
        }

        match ($event->type) {
            WebhookEvent::TYPE_SUCCEEDED => $this->orders->transition($order, Order::STATUS_PAID),
            WebhookEvent::TYPE_REFUNDED  => $this->orders->transition($order, Order::STATUS_REFUNDED),
            default => null,
        };

        return response()->json(['status' => 'processed']);
    }
}
