<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\OrderService;
use App\Domain\Payment\GatewayResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly GatewayResolver $gateways,
    ) {}

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'string'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.line1' => ['required', 'string'],
            'shipping_address.city' => ['required', 'string'],
            'shipping_address.country' => ['required', 'string'],
            'billing_address' => ['required', 'array'],
        ]);

        if (!in_array($data['gateway'], $this->gateways->available(), true)) {
            return response()->json([
                'error' => 'Unsupported gateway',
                'available' => $this->gateways->available(),
            ], 422);
        }

        $order = $this->orders->createFromCart(
            $request->user(),
            $data['shipping_address'],
            $data['billing_address'],
            $data['gateway'],
        );

        $intent = $this->gateways->get($data['gateway'])->createIntent($order);
        $this->orders->attachGatewayIntent($order, $intent->id);

        return response()->json([
            'order' => $order->fresh('items'),
            'payment' => $intent->toArray(),
        ], 201);
    }
}
