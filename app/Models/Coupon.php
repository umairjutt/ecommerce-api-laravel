<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'type', 'value', 'min_subtotal_cents', 'max_redemptions', 'redemptions', 'expires_at', 'is_active'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isUsable(int $subtotalCents): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_redemptions && $this->redemptions >= $this->max_redemptions) return false;
        if ($subtotalCents < $this->min_subtotal_cents) return false;
        return true;
    }

    public function discountFor(int $subtotalCents): int
    {
        return match ($this->type) {
            'percent' => (int) round($subtotalCents * $this->value / 100),
            'fixed' => min((int) $this->value, $subtotalCents),
            default => 0,
        };
    }
}
