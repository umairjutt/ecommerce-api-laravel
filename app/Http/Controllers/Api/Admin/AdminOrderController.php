<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Order\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with('items')
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function transition(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string']]);
        $this->orders->transition($order, $data['status']);
        return response()->json(['order' => $order->fresh('items')]);
    }
}
