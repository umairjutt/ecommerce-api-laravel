<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);
        $tax = (int) round($subtotal * 0.1);
        $shipping = 500;

        return [
            'user_id' => User::factory(),
            'reference' => 'ORD-' . strtoupper(Str::random(10)),
            'status' => Order::STATUS_PENDING,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'shipping_cents' => $shipping,
            'total_cents' => $subtotal + $tax + $shipping,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'gateway_intent_id' => 'pi_' . Str::random(16),
            'shipping_address' => ['line1' => fake()->streetAddress(), 'city' => fake()->city()],
            'billing_address' => ['line1' => fake()->streetAddress(), 'city' => fake()->city()],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_PAID]);
    }
}
