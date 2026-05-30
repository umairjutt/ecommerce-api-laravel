<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name_snapshot,
            'qty' => $this->qty,
            'unit_price_cents' => $this->unit_price_cents,
            'line_total_cents' => $this->qty * $this->unit_price_cents,
        ];
    }
}
