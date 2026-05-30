<?php

namespace App\Domain\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreate(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(Cart $cart, int $productId, int $qty): CartItem
    {
        $product = Product::findOrFail($productId);

        if (!$product->is_active) {
            throw ValidationException::withMessages(['product_id' => 'Product unavailable.']);
        }

        if (!$product->inStock($qty)) {
            throw ValidationException::withMessages(['qty' => 'Insufficient stock.']);
        }

        $item = $cart->items()->where('product_id', $productId)->first();

        if ($item) {
            $item->qty += $qty;
            $item->save();
        } else {
            $item = $cart->items()->create([
                'product_id' => $productId,
                'qty' => $qty,
                'unit_price_cents' => $product->price_cents,
            ]);
        }

        return $item;
    }

    public function removeItem(Cart $cart, int $itemId): void
    {
        $cart->items()->where('id', $itemId)->delete();
    }

    public function applyCoupon(Cart $cart, string $code): void
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon || !$coupon->isUsable($cart->subtotal())) {
            throw ValidationException::withMessages(['coupon' => 'Invalid or expired coupon.']);
        }

        $cart->coupon_code = $code;
        $cart->save();
    }

    public function totals(Cart $cart): array
    {
        $cart->load('items');

        $subtotal = $cart->subtotal();
        $discount = 0;

        if ($cart->coupon_code && $coupon = Coupon::where('code', $cart->coupon_code)->first()) {
            if ($coupon->isUsable($subtotal)) {
                $discount = $coupon->discountFor($subtotal);
            }
        }

        $taxable = max(0, $subtotal - $discount);
        $tax = (int) round($taxable * (float) config('shop.tax_rate', 0.10));
        $shipping = $taxable > 5000 ? 0 : 500;

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'tax_cents' => $tax,
            'shipping_cents' => $shipping,
            'total_cents' => $taxable + $tax + $shipping,
            'currency' => config('shop.currency', 'USD'),
        ];
    }
}
