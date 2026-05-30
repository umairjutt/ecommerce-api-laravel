<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'currency' => $this->currency,
            'totals' => [
                'subtotal_cents' => $this->subtotal_cents,
                'tax_cents' => $this->tax_cents,
                'shipping_cents' => $this->shipping_cents,
                'total_cents' => $this->total_cents,
            ],
            'gateway' => $this->gateway,
            'gateway_intent_id' => $this->gateway_intent_id,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
