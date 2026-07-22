# GauthierFitness - Backend

> REST API for the GauthierFitness e-commerce store. Handles the catalog, cart, Stripe payments, 3D customization
> sessions, AI design generation, and administration.

Repo: `CharlesGAUTHIER1999/gauthierfitness-backend` &nbsp;·&nbsp; Production: <https://api.gauthierfitness.fr>

> Cross-project documentation (architecture, deployment, user manual, upgrades): [meta-repo
`gauthierfitness/docs`](https://github.com/CharlesGAUTHIER1999/gauthierfitness/tree/main/docs)

---

## Stack

| Layer     | Technology                                               |
|-----------|----------------------------------------------------------|
| Runtime   | PHP 8.3                                                  |
| Framework | Laravel 13                                               |
| Database  | MySQL 8                                                  |
| API Auth  | Laravel Sanctum (Bearer token)                           |
| Payment   | Stripe (PaymentIntents + signed webhook)                 |
| AI        | OpenAI Images                                            |
| API Docs  | [Scramble](https://scramble.dedoc.co) (auto OpenAPI 3.1) |
| Tests     | PHPUnit 11 (SQLite in-memory in CI)                      |
| Style     | Laravel Pint (PSR-12)                                    |
| CI/CD     | GitHub Actions → GHCR image → infra dispatch             |

---

## Local setup

### With Docker (recommended)

No local MySQL installation required.

```bash
cp .env.example .env
cp .env.docker.example .env.docker    # config injected into the app container
docker compose up -d --wait   # waits for MySQL + the app to actually be ready (build included on first run, ~2-4 min)
docker compose exec app php artisan key:generate --show   # copy the displayed key into APP_KEY= in .env.docker
docker compose up -d --force-recreate app                 # reloads the container with the new key
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

API available at `http://localhost:8000` (health check: `http://localhost:8000/api/health`), Swagger docs at
`http://localhost:8000/docs/api`, MySQL on `localhost:3308` (mapped port to avoid a conflict with a local MySQL).

Two distinct `Dockerfile`s, for two different uses:

| File                | Use                                                                       |
|---------------------|---------------------------------------------------------------------------|
| `Dockerfile` (root) | Multi-stage, built by CI (`target: production`) → image published to GHCR |
| `docker/Dockerfile` | Single-stage, used by `docker-compose.yml` for a local dev environment    |

### Without Docker

Requires a MySQL 8 server already installed and running locally, with a database and user already created —
`.env.example` contains placeholder credentials (`your_db_user` / `your_db_password`) to recreate as-is or
adapt to your own (
details: [docs/02-deployment.md § 3](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/02-deployment.md#3-local-startup)).

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link   # required for product images to be served

# Runs server, queue, pail, and vite simultaneously
composer dev
```

API exposed at `http://localhost:8000`, Swagger docs at `http://localhost:8000/docs/api`.

### Key environment variables

| Var                                       | Use                                                 |
|-------------------------------------------|-----------------------------------------------------|
| `APP_ENV`                                 | `local` / `staging` / `production`                  |
| `DB_*`                                    | MySQL connection                                    |
| `SANCTUM_STATEFUL_DOMAINS`                | Domains allowed to use the Sanctum cookie           |
| `CORS_ALLOWED_ORIGINS`                    | Allowed frontend origins (empty = localhost in dev) |
| `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe                                              |
| `OPENAI_API_KEY`                          | OpenAI Images                                       |
| `MAIL_*`                                  | SMTP (Mailpit locally, OVH in production)           |
| `API_VERSION`                             | Version exposed in the OpenAPI spec                 |

Full
table → [docs/02-deployment.md § 4](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/02-deployment.md#4-environment-variables).

---

## Code structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/        Admin products, orders, stock
│   │   ├── AI/           AIDesignController (AI generation)
│   │   ├── Auth/         Login/Register/Logout/Verification
│   │   ├── Cart/         CartController
│   │   ├── Catalog/      ProductController
│   │   ├── Customization/CustomizationController, CustomizationAssetController
│   │   ├── Orders/       OrderController
│   │   ├── Payments/     StripeController          ← PaymentIntent + webhook
│   │   └── Support/      ContactController
│   ├── Middleware/       AdminMiddleware (guards /admin routes)
│   ├── Requests/         FormRequests (validation + authorization)
│   └── Resources/        API Resources (ProductResource)
├── Models/               Eloquent: User, Cart, Order, Product, StockLot, …
├── Notifications/        Transactional emails (OrderConfirmed, OrderStatusUpdated)
├── Providers/            AppServiceProvider (RateLimiter, Scramble security)
└── Services/
    ├── AI/               OpenAIImageService, OpenAIModerationService, ModerationThresholdEvaluator, PromptBlocklist
    ├── Cart/             CartMergeService
    ├── Pricing/           CartPricingCalculator
    └── Stock/             StockAllocator

routes/api.php            43 routes (3 groups: public / auth:sanctum / auth:sanctum + admin)
database/migrations/      Versioned schema
swagger/openapi.json      OpenAPI 3.1 spec regenerated by Scramble
tests/                    Feature + Unit (SQLite in-memory)
```

---

## Conventions

- **Validation**: `Illuminate\Http\Request::validate()` for simple rules, a dedicated FormRequest when cross-field
  validation is needed (see `AddToCartRequest::withValidator`).
- **Responses**: typed `JsonResponse` returned. API Resources for complex structures.
- **OpenAPI docs**: Scramble reads type-hints, FormRequests, and docblocks. To group endpoints, PHP 8 attribute
  `#[Group(name: 'Authentification', weight: 1)]` on the controller class. For responses:
  `@response 200 scenario="..." {...}` in the docblock.
- **Scramble security**: the global Sanctum scheme config + public/private flagging is done in [
  `app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php), **not** in `config/scramble.php`. The
  `SecurityScheme` objects aren't `var_export`-serializable, which would crash `php artisan config:cache` in
  production.
- **Price snapshotting**: `cart_items` and `order_items` carry the price at the time of the action, never re-read from
  the product.
- **Webhook idempotency**: `webhook_events` table indexed on `(provider, provider_event_id)`.

---

## Tests and quality

```bash
php artisan test                  # PHPUnit (SQLite in-memory in CI)
./vendor/bin/pint                 # PSR-12 formatting (auto-fix)
./vendor/bin/pint --test          # Check without fixing (CI)
```

CI: `phpunit` → `lint` → `build GHCR image` → `dispatch infra`. See [
`.github/workflows/ci-cd.yml`](.github/workflows/ci-cd.yml).

---

## OpenAPI documentation

Documentation is generated **from the code** by Scramble - there's nothing to maintain by hand for the endpoint
structure.

```bash
# Regenerate the spec
php artisan scramble:export       # → swagger/openapi.json

# View it locally
php artisan serve
# → http://localhost:8000/docs/api
```

Supported annotations on controllers: `#[Group(name, weight)]`, docblock summary, `@response`, `@queryParam`,
`@urlParam`, `@unauthenticated`.
Details: [docs/05-api.md](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/05-api.md).

---

## Branching convention

- `feature`: `GF{n}-{ShortName}` (e.g. `GF21-SwaggerDoc`, `GF22-Documentation`)
- `develop`: automatic push → image `ghcr.io/.../gauthierfitness-backend:develop` → infra deploys staging
- `main`: push → `:latest` image + SHA tag → manual production trigger

---

## Useful links

- [Deployment manual](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/02-deployment.md)
- [Upgrade manual](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/04-upgrade.md)
- [Detailed architecture](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/01-architecture.md)
- [Frontend repo](https://github.com/CharlesGAUTHIER1999/gauthierfitness-frontend)
- [Infra repo](https://github.com/CharlesGAUTHIER1999/gauthierfitness-infra)
