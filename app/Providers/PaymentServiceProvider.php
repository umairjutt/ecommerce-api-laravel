<?php

namespace App\Providers;

use App\Domain\Payment\GatewayResolver;
use App\Domain\Payment\Gateways\PaypalGateway;
use App\Domain\Payment\Gateways\RazorpayGateway;
use App\Domain\Payment\Gateways\StripeGateway;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GatewayResolver::class, function () {
            $resolver = new GatewayResolver();

            if ($key = config('services.stripe.secret')) {
                $resolver->register(new StripeGateway(
                    new StripeClient($key),
                    config('services.stripe.webhook_secret') ?? '',
                ));
            }

            if ($id = config('services.paypal.client_id')) {
                $resolver->register(new PaypalGateway(
                    $id,
                    config('services.paypal.client_secret'),
                    config('services.paypal.mode', 'sandbox'),
                ));
            }

            if ($key = config('services.razorpay.key')) {
                $resolver->register(new RazorpayGateway(
                    $key,
                    config('services.razorpay.secret'),
                    config('services.razorpay.webhook_secret') ?? '',
                ));
            }

            return $resolver;
        });
    }
}
