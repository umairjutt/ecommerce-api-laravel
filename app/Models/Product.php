<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'sku', 'price_cents',
        'currency', 'stock', 'category_id', 'is_active', 'image_url',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function priceFormatted(): string
    {
        return number_format($this->price_cents / 100, 2) . ' ' . $this->currency;
    }

    public function inStock(int $qty = 1): bool
    {
        return $this->stock >= $qty;
    }
}
