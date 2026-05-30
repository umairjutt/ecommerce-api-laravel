<?php

namespace App\Domain\Order;

use App\Domain\Cart\CartService;
use App\Domain\Payment\GatewayResolver;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly GatewayResolver $gateways,
    ) {}

    public function createFromCart(User $user, array $shippingAddress, array $billingAddress, string $gateway): Order
    {
        $cart = $this->carts->getOrCreate($user);
        $cart->load('items.product');

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Cart is empty.']);
        }

        return DB::transaction(function () use ($cart, $user, $shippingAddress, $billingAddress, $gateway) {
            $this->reserveStock($cart);

            $totals = $this->carts->totals($cart);

            $order = Order::create([
                'user_id' => $user->id,
                'reference' => 'ORD-' . strtoupper(Str::random(10)),
                'status' => Order::STATUS_PENDING,
                'subtotal_cents' => $totals['subtotal_cents'],
                'tax_cents' => $totals['tax_cents'],
                'shipping_cents' => $totals['shipping_cents'],
                'total_cents' => $totals['total_cents'],
                'currency' => $totals['currency'],
                'gateway' => $gateway,
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress,
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'name_snapshot' => $item->product->name,
                    'qty' => $item->qty,
                    'unit_price_cents' => $item->unit_price_cents,
                ]);
            }

            $cart->items()->delete();
            $cart->coupon_code = null;
            $cart->save();

            return $order;
        });
    }

    public function transition(Order $order, string $newStatus): Order
    {
        if (!$order->canTransitionTo($newStatus)) {
            throw new \DomainException("Illegal transition {$order->status} -> {$newStatus}");
        }

        $order->status = $newStatus;
        $order->save();

        return $order;
    }

    public function attachGatewayIntent(Order $order, string $intentId): void
    {
        $order->gateway_intent_id = $intentId;
        $order->save();
    }

    /**
     * Issue a (full or partial) refund through the order's payment gateway and
     * transition the order to `refunded`. Stock for refunded items is restored.
     *
     * @param  int|null  $amountCents  Null for a full refund.
     *
     * @throws \DomainException        When the order cannot be refunded.
     * @throws ValidationException     When there is no captured payment to refund.
     */
    public function refund(Order $order, ?int $amountCents = null): Order
    {
        if (! $order->canTransitionTo(Order::STATUS_REFUNDED)) {
            throw new \DomainException("Order {$order->reference} cannot be refunded from status {$order->status}.");
        }

        if (! $order->gateway_intent_id) {
            throw ValidationException::withMessages([
                'order' => 'No captured payment intent is attached to this order.',
            ]);
        }

        if ($amountCents !== null && ($amountCents <= 0 || $amountCents > $order->total_cents)) {
            throw ValidationException::withMessages([
                'amount_cents' => 'Refund amount must be between 1 and the order total.',
            ]);
        }

        $gateway = $this->gateways->get($order->gateway);

        return DB::transaction(function () use ($gateway, $order, $amountCents) {
            $succeeded = $gateway->refund($order->gateway_intent_id, $amountCents);

            if (! $succeeded) {
                throw new \RuntimeException("Gateway {$order->gateway} declined the refund.");
            }

            // Full refunds restock the order; partial refunds leave stock as-is.
            if ($amountCents === null) {
                $this->restock($order);
            }

            $order->status = Order::STATUS_REFUNDED;
            $order->save();

            Log::info('order.refunded', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'gateway' => $order->gateway,
                'amount_cents' => $amountCents ?? $order->total_cents,
            ]);

            return $order;
        });
    }

    private function restock(Order $order): void
    {
        foreach ($order->items()->get() as $item) {
            Product::whereKey($item->product_id)->increment('stock', $item->qty);
        }
    }

    /**
     * Atomic stock decrement using row-level locks. Prevents oversell under
     * concurrent checkout from many users.
     */
    private function reserveStock(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $fresh = Product::lockForUpdate()->find($item->product_id);

            if (!$fresh || !$fresh->inStock($item->qty)) {
                throw ValidationException::withMessages([
                    'stock' => "Product '{$fresh?->name}' is out of stock.",
                ]);
            }

            $fresh->decrement('stock', $item->qty);
        }
    }
}
