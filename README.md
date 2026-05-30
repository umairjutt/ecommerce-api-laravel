# E-commerce API — Laravel 11 (Flagship)

A production-grade, scalable e-commerce REST API. Headless by design — bring your own storefront (mobile, React, Vue, Flutter). This is the kind of backend a senior Laravel engineer would ship for a real client.

[Live demo (TODO)](https://example.com) · [Swagger docs](http://localhost:8000/docs/api) · [Postman collection](docs/postman_collection.json)

## What this demonstrates

- **Clean Architecture** — Services + Repositories, no fat controllers
- **Multi-gateway payments** — Stripe, PayPal, Razorpay behind one `PaymentGateway` interface (Strategy pattern), with webhook signature verification and idempotency
- **RBAC** — 4 roles (guest, customer, vendor, admin) with policy-based authorization
- **Catalog** — products, categories, attributes, variants, full-text search
- **Cart engine** — guest -> authenticated cart merge, coupon codes, tax/shipping calculation
- **Order state machine** — `pending -> paid -> fulfilled -> shipped -> delivered` (+ refund path)
- **Atomic inventory** — DB-level row locking on stock decrement (no oversell)
- **Queue workers** — order emails, webhook processing, low-stock alerts via Horizon
- **Redis caching** — tagged cache for product listings with observer-driven invalidation
- **Custom rate limiter** — uses [`umair/redis-rate-limiter`](../redis-rate-limiter) package
- **OpenAPI docs** — auto-generated from controller annotations via Scribe
- **Pest tests** — feature tests for every endpoint, unit tests for services

## Architecture

```mermaid
flowchart TB
    client[Mobile / Web Client] -->|REST| api[Laravel API]

    subgraph laravel [Laravel Layer]
      api --> ctrl[Controllers]
      ctrl --> svc[Services]
      svc --> repo[Repositories]
      svc --> events[Events]
    end

    repo --> mysql[(MySQL)]
    svc --> redis[(Redis cache + queue)]

    api --> stripe[Stripe API]
    api --> paypal[PayPal API]
    api --> razor[Razorpay API]

    stripe -.webhook.-> api
    paypal -.webhook.-> api
    razor  -.webhook.-> api

    events --> queue[Redis Queue]
    queue --> worker[Horizon Workers]
    worker --> mail[Mail Queue]
    worker --> stock[Inventory updates]
```

## Module map

| Module       | Path                                                 |
|--------------|------------------------------------------------------|
| Auth & RBAC  | `app/Http/Controllers/Api/Auth/`                     |
| Catalog      | `app/Domain/Catalog/`                                |
| Cart         | `app/Domain/Cart/`                                   |
| Checkout     | `app/Domain/Checkout/`                               |
| Payments     | `app/Domain/Payment/Gateways/` (Strategy interface)  |
| Orders       | `app/Domain/Order/`                                  |
| Webhooks     | `app/Http/Controllers/Api/WebhookController.php`     |

## Tech stack

- Laravel 11, PHP 8.3
- MySQL 8 (primary) + Redis 7 (cache + queue + sessions)
- Laravel Sanctum (token auth)
- Spatie Laravel Permission (RBAC)
- Laravel Horizon (queue dashboard)
- Stripe PHP SDK, PayPal REST SDK, Razorpay PHP SDK
- Scribe (OpenAPI generation)
- Pest 3 (tests)

## Quick start

```bash
git clone <repo>
cd ecommerce-api-laravel
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
# API: http://localhost:8000
# Swagger: http://localhost:8000/docs/api
# Horizon: http://localhost:8000/horizon
```

Seed creates:
- 1000 products across 12 categories
- Admin: admin@shop.test / password
- Customer: customer@shop.test / password
- Sample coupons: `WELCOME10`, `FREESHIP`

## Key API endpoints

| Method | Path                          | Role        |
|--------|-------------------------------|-------------|
| GET    | `/api/products`               | public      |
| GET    | `/api/products/{slug}`        | public      |
| POST   | `/api/cart/items`             | customer    |
| POST   | `/api/checkout`               | customer    |
| POST   | `/api/payments/{gateway}/init`| customer    |
| POST   | `/api/webhooks/{gateway}`     | gateway     |
| GET    | `/api/orders`                 | customer    |
| GET    | `/api/admin/orders`           | admin       |

Full list in [Swagger](http://localhost:8000/docs/api).

## Key implementation highlights

- **Strategy-based gateways** — [app/Domain/Payment/Gateways/PaymentGateway.php](app/Domain/Payment/Gateways/PaymentGateway.php) + implementations (Stripe/PayPal/Razorpay) so adding a new gateway is one class
- **Idempotent webhooks** — see `WebhookController::handle()` (idempotency key checked in Redis before processing)
- **Atomic stock decrement** — `OrderService::reserveStock()` uses `lockForUpdate()` in a transaction
- **Cache invalidation** — `ProductObserver` flushes tagged Redis cache on save/delete

## Tests

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pest --coverage
```

## Roadmap / known limitations

- Search uses MySQL full-text; switch to Meilisearch/Typesense for production scale
- No multi-currency yet (single base currency)
- Email templates are unstyled HTML; production should use MJML or React Email
- Refunds are gateway-mediated only; no partial-refund UI yet

## Deploy

See [DEPLOY.md](DEPLOY.md) for Railway + managed MySQL + managed Redis setup.

<!-- ownership:author -->
---

## Author

**Umair** &mdash; [@umairjutt](https://github.com/umairjutt)

Designed, built and maintained by me. Licensed under the [MIT License](LICENSE).