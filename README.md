# E-commerce API — Laravel 11

[CI results](https://github.com/umairjutt/ecommerce-api-laravel/actions) · [Engineering guide](PORTFOLIO.md)

> **Status:** Development commerce API. Payment lifecycle validation, retry/concurrency handling and standalone setup require further work before real transactions.

[![CI](https://github.com/umairjutt/ecommerce-api-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/umairjutt/ecommerce-api-laravel/actions/workflows/ci.yml)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![License: MIT](https://img.shields.io/badge/license-MIT-green)

A headless Laravel commerce API exploring catalog management, cart calculations, order state transitions and payment adapter design.

[API routes](routes/api.php) · [Tests](tests)

## What this demonstrates

- **Domain organization** — services separate cart, order and payment logic from HTTP handling
- **Multi-gateway payments** — Stripe, PayPal, Razorpay behind one `PaymentGateway` interface (Strategy pattern), with provider-specific webhook parsing; complete signature and lifecycle verification is required before real payments
- **RBAC** — 4 roles (guest, customer, vendor, admin) with policy-based authorization
- **Catalog** — products, categories, attributes, variants, full-text search
- **Cart engine** — guest -> authenticated cart merge, coupon codes, tax/shipping calculation
- **Order state machine** — `pending -> paid -> fulfilled -> shipped -> delivered` (+ refund path)
- **Stock reservation** — row locks guard stock decrement in the order transaction; concurrency regression coverage remains
- **Queue infrastructure** — Horizon and Redis configuration for asynchronous workflows
- **Redis caching** — tagged cache for product listings with observer-driven invalidation
- **Custom rate limiter** — uses [`umair/redis-rate-limiter`](https://github.com/umairjutt/redis-rate-limiter) package
- **OpenAPI docs** — auto-generated from controller annotations via Scribe
- **Pest tests** — selected feature and unit scenarios; see `tests/` for current scope

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
git clone https://github.com/umairjutt/ecommerce-api-laravel.git
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

| Method | Path                                   | Role        | Notes |
|--------|----------------------------------------|-------------|-------|
| GET    | `/api/products`                        | public      | Cached, filterable; returns `ProductResource` |
| GET    | `/api/products/{slug}`                 | public      | |
| GET    | `/api/metrics`                         | public      | Prometheus text exposition |
| POST   | `/api/cart/items`                      | customer    | |
| POST   | `/api/checkout`                        | customer    | Accepts `Idempotency-Key`; rate-limited 10/min |
| POST   | `/api/webhooks/{gateway}`              | gateway     | Signature-verified, de-duplicated |
| GET    | `/api/orders`                          | customer    | Returns `OrderResource` |
| GET    | `/api/admin/orders`                    | admin       | |
| POST   | `/api/admin/orders/{order}/transition` | admin       | State-machine guarded |
| POST   | `/api/admin/orders/{order}/refund`     | admin       | Full/partial gateway refund + restock |

Full list in [Swagger](http://localhost:8000/docs/api).

### Idempotent checkout

`POST /api/checkout` accepts an `Idempotency-Key` header. The first request is
processed and its response cached in Redis for 24h; any retry with the same key
replays the stored response (header `Idempotent-Replayed: true`) instead of
creating a second order or double-charging the customer.

## Key implementation highlights

- **Strategy-based gateways** — [app/Domain/Payment/Gateways/PaymentGateway.php](app/Domain/Payment/Gateways/PaymentGateway.php) + implementations (Stripe/PayPal/Razorpay) so adding a new gateway is one class
- **Idempotent checkout** — [app/Http/Middleware/IdempotencyKey.php](app/Http/Middleware/IdempotencyKey.php) replays cached responses for repeated `Idempotency-Key`s with a Redis lock to guard concurrent duplicates
- **Webhook deduplication example** — a Redis event key is used; durable processing and retry semantics remain
- **Refund adapter example** — provider dispatch is implemented; partial-refund state and concurrent/retry semantics remain
- **Atomic stock decrement** — `OrderService::reserveStock()` uses `lockForUpdate()` in a transaction
- **Cache invalidation** — `ProductObserver` flushes tagged Redis cache on save/delete
- **Custom rate limiter** — the sibling [`umair/redis-rate-limiter`](https://github.com/umairjutt/redis-rate-limiter) package (sliding window) guards checkout via the `rate.limit` middleware
- **Stable API contracts** — `ProductResource` / `OrderResource` decouple the JSON shape from Eloquent models
- **Observability** — `X-Request-Id` on every request, structured JSON logs, and `/api/metrics`

## Tests

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pest --coverage
```

## Roadmap / known limitations

- Search uses MySQL full-text; switch to Meilisearch/Typesense for production scale
- No multi-currency yet (single base currency)
- Email templates are unstyled HTML; production should use MJML or React Email
- Refund adapters need provider-specific identifier, lifecycle, partial-refund and concurrency validation

## Deploy

The Compose configuration is for local development; production deployment documentation remains to be completed.

<!-- ownership:author -->
---

## Author

**Umair** &mdash; [@umairjutt](https://github.com/umairjutt)

Maintained by [Umair Jutt](https://github.com/umairjutt). See [LICENSE](LICENSE) for repository licensing.
