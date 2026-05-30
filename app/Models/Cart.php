<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'session_id', 'coupon_code'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function subtotal(): int
    {
        return $this->items->sum(fn ($i) => $i->qty * $i->unit_price_cents);
    }
}
