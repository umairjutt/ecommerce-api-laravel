<?php

namespace App\Domain\Order;

use App\Domain\Cart\CartService;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly CartService $carts) {}

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
