<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Orders
 *
 * Read the authenticated customer's own orders.
 * @authenticated
 */
class OrderController extends Controller
{
    /**
     * List my orders
     *
     * @apiResourceCollection App\Http\Resources\OrderResource
     * @apiResourceModel App\Models\Order
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()->with('items')->latest()->paginate(20);

        return OrderResource::collection($orders);
    }

    /**
     * Get one of my orders
     *
     * @urlParam order integer required The order id. Example: 1
     * @apiResource App\Http\Resources\OrderResource
     * @apiResourceModel App\Models\Order
     */
    public function show(Request $request, Order $order): OrderResource
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        return new OrderResource($order->load('items'));
    }
}
