<?php

namespace App\Domain\Payment;

final class WebhookEvent
{
    public const TYPE_SUCCEEDED = 'payment.succeeded';
    public const TYPE_FAILED = 'payment.failed';
    public const TYPE_REFUNDED = 'payment.refunded';

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $intentId,
        public readonly array $raw = [],
    ) {}
}
