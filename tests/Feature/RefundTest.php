<?php

use App\Domain\Order\OrderService;
use App\Domain\Payment\GatewayResolver;
use App\Domain\Payment\Gateways\PaymentGateway;
use App\Domain\Payment\PaymentIntent;
use App\Domain\Payment\WebhookEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * In-memory gateway so refund tests never touch Stripe.
 */
class FakeGateway implements PaymentGateway
{
    public array $refundCalls = [];
    public bool $succeed = true;

    public function name(): string
    {
        return 'fake';
    }

    public function createIntent(Order $order): PaymentIntent
    {
        return new PaymentIntent('pi_fake', 'secret', $order->total_cents, $order->currency, 'fake', []);
    }

    public function parseWebhook(string $payload, array $headers): WebhookEvent
    {
        return new WebhookEvent('evt_fake', WebhookEvent::TYPE_SUCCEEDED, 'pi_fake', []);
    }

    public function refund(string $intentId, ?int $amountCents = null): bool
    {
        $this->refundCalls[] = ['intent' => $intentId, 'amount' => $amountCents];
        return $this->succeed;
    }
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'customer']);

    $this->fakeGateway = new FakeGateway();
    $this->app->singleton(GatewayResolver::class, function () {
        $resolver = new GatewayResolver();
        $resolver->register($this->fakeGateway);
        return $resolver;
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function paidOrder(): Order
{
    return Order::factory()->paid()->create([
        'gateway' => 'fake',
        'gateway_intent_id' => 'pi_fake',
    ]);
}

test('admin can fully refund a paid order and it is restocked', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = paidOrder();
    $order->items()->create([
        'product_id' => $product->id,
        'name_snapshot' => $product->name,
        'qty' => 2,
        'unit_price_cents' => 1000,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/orders/{$order->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_REFUNDED);

    expect($this->fakeGateway->refundCalls)->toHaveCount(1);
    expect($product->fresh()->stock)->toBe(7);
});

test('partial refund does not restock', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = paidOrder();
    $order->items()->create([
        'product_id' => $product->id,
        'name_snapshot' => $product->name,
        'qty' => 2,
        'unit_price_cents' => 1000,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/orders/{$order->id}/refund", ['amount_cents' => 500])
        ->assertOk();

    expect($this->fakeGateway->refundCalls[0]['amount'])->toBe(500);
    expect($product->fresh()->stock)->toBe(5);
});

test('cannot refund a pending order', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PENDING,
        'gateway' => 'fake',
        'gateway_intent_id' => 'pi_fake',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/orders/{$order->id}/refund")
        ->assertStatus(500); // DomainException -> illegal transition

    expect($this->fakeGateway->refundCalls)->toBeEmpty();
});

test('service refund transitions order to refunded', function () {
    $order = paidOrder();
    $service = app(OrderService::class);

    $service->refund($order);

    expect($order->fresh()->status)->toBe(Order::STATUS_REFUNDED);
});
