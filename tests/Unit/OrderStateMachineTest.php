<?php

use App\Models\Order;

test('state machine allows valid transitions', function () {
    $o = new Order(['status' => Order::STATUS_PENDING]);
    expect($o->canTransitionTo(Order::STATUS_PAID))->toBeTrue();
    expect($o->canTransitionTo(Order::STATUS_CANCELLED))->toBeTrue();
});

test('state machine blocks invalid transitions', function () {
    $o = new Order(['status' => Order::STATUS_DELIVERED]);
    expect($o->canTransitionTo(Order::STATUS_PENDING))->toBeFalse();
});

test('paid can go to fulfilled or refunded only', function () {
    $o = new Order(['status' => Order::STATUS_PAID]);
    expect($o->canTransitionTo(Order::STATUS_FULFILLED))->toBeTrue();
    expect($o->canTransitionTo(Order::STATUS_REFUNDED))->toBeTrue();
    expect($o->canTransitionTo(Order::STATUS_SHIPPED))->toBeFalse();
});
