<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cart\CartService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Cart
 *
 * Manage the authenticated user's cart, line items, and coupons.
 * @authenticated
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->getOrCreate($request->user());
        $cart->load('items.product');
        return response()->json([
            'cart' => $cart,
            'totals' => $this->carts->totals($cart),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart = $this->carts->getOrCreate($request->user());
        $this->carts->addItem($cart, $data['product_id'], $data['qty']);

        return response()->json(['cart' => $cart->fresh('items.product')], 201);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->carts->getOrCreate($request->user());
        $this->carts->removeItem($cart, $itemId);
        return response()->json(['cart' => $cart->fresh('items.product')]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $cart = $this->carts->getOrCreate($request->user());
        $this->carts->applyCoupon($cart, $data['code']);
        return response()->json(['totals' => $this->carts->totals($cart)]);
    }
}
