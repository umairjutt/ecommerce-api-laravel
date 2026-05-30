<?php

namespace App\Domain\Payment;

final class PaymentIntent
{
    public function __construct(
        public readonly string $id,
        public readonly string $clientSecret,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $gateway,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_secret' => $this->clientSecret,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
        ];
    }
}
