<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id', 'reference', 'status', 'subtotal_cents', 'tax_cents',
        'shipping_cents', 'total_cents', 'currency', 'gateway',
        'gateway_intent_id', 'shipping_address', 'billing_address',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'total_cents' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canTransitionTo(string $status): bool
    {
        $allowed = [
            self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
            self::STATUS_PAID => [self::STATUS_FULFILLED, self::STATUS_REFUNDED],
            self::STATUS_FULFILLED => [self::STATUS_SHIPPED, self::STATUS_REFUNDED],
            self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
        ];
        return in_array($status, $allowed[$this->status] ?? [], true);
    }
}
