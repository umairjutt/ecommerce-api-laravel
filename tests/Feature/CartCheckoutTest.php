<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer']);
    Role::firstOrCreate(['name' => 'admin']);

    $this->user = User::create(['name' => 'C', 'email' => 'c@c.com', 'password' => 'password']);
    $this->user->assignRole('customer');

    $this->product = Product::create([
        'name' => 'Widget',
        'slug' => 'widget',
        'sku' => 'SKU-1',
        'price_cents' => 1500,
        'currency' => 'USD',
        'stock' => 10,
        'is_active' => true,
    ]);
});

test('customer can add product to cart', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/cart/items', ['product_id' => $this->product->id, 'qty' => 2])
        ->assertCreated();
});

test('cart totals include tax and shipping', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/cart/items', ['product_id' => $this->product->id, 'qty' => 1]);

    $response = $this->getJson('/api/cart');
    $response->assertOk()
        ->assertJsonPath('totals.subtotal_cents', 1500)
        ->assertJsonPath('totals.tax_cents', 150)
        ->assertJsonPath('totals.shipping_cents', 500);
});

test('cannot add more items than stock', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/cart/items', ['product_id' => $this->product->id, 'qty' => 999])
        ->assertUnprocessable();
});

test('coupon discount is applied', function () {
    \App\Models\Coupon::create(['code' => 'TEST10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'min_subtotal_cents' => 0]);

    $this->actingAs($this->user, 'sanctum');
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'qty' => 1]);
    $this->postJson('/api/cart/coupon', ['code' => 'TEST10'])->assertOk();

    $this->getJson('/api/cart')->assertJsonPath('totals.discount_cents', 150);
});
