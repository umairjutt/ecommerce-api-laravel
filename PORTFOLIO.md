# Engineering guide

Laravel commerce API exploring catalog, carts, order workflows, inventory and payment adapter design.

## Code tour

- [app/Domain/Order/OrderService.php](app/Domain/Order/OrderService.php)
- [app/Domain/Payment/Gateways/PaymentGateway.php](app/Domain/Payment/Gateways/PaymentGateway.php)
- [app/Http/Controllers/Api/WebhookController.php](app/Http/Controllers/Api/WebhookController.php)
- [tests](tests)

## Scope and verification

Development commerce API. Payment lifecycle validation, retry/concurrency handling and standalone setup require further work before real transactions.

[Current CI runs](https://github.com/umairjutt/ecommerce-api-laravel/actions). A workflow definition or build result does not establish that all runtime scenarios pass. The README contains development commands; unfinished setup and behavior need completion before production deployment.

## Maintainer

[Umair Jutt](https://github.com/umairjutt). Frameworks and dependencies retain their own ownership and licensing.
