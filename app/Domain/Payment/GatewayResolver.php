<?php

namespace App\Domain\Payment;

use App\Domain\Payment\Gateways\PaymentGateway;
use InvalidArgumentException;

class GatewayResolver
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->name()] = $gateway;
    }

    public function get(string $name): PaymentGateway
    {
        if (!isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Unknown payment gateway: {$name}");
        }
        return $this->gateways[$name];
    }

    public function available(): array
    {
        return array_keys($this->gateways);
    }
}
