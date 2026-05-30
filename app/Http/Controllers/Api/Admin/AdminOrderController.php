<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Order\OrderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Admin
 *
 * Administrative order operations: list all orders, transition status, and
 * issue gateway refunds. Requires the `admin` role.
 * @authenticated
 */
class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * List all orders
     *
     * @queryParam status string Filter by order status. Example: paid
     * @apiResourceCollection App\Http\Resources\OrderResource
     * @apiResourceModel App\Models\Order
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with('items')
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    /**
     * Transition an order
     *
     * Move an order to a new status using the server-side state machine.
     *
     * @urlParam order integer required The order id. Example: 1
     * @bodyParam status string required Target status. Example: fulfilled
     * @apiResource App\Http\Resources\OrderResource
     * @apiResourceModel App\Models\Order
     */
    public function transition(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string']]);
        $this->orders->transition($order, $data['status']);

        return (new OrderResource($order->fresh('items')))->response();
    }

    /**
     * Refund an order
     *
     * Issue a full or partial refund through the order's payment gateway and
     * transition it to `refunded`. Full refunds restock the line items.
     *
     * @urlParam order integer required The order id. Example: 1
     * @bodyParam amount_cents integer Optional partial refund amount in minor units; omit for a full refund. Example: 500
     * @apiResource App\Http\Resources\OrderResource
     * @apiResourceModel App\Models\Order
     * @response 422 {"message": "No captured payment intent is attached to this order."}
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->orders->refund($order, $data['amount_cents'] ?? null);

        return (new OrderResource($order->fresh('items')))->response();
    }
}
