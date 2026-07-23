# Changelog

All notable changes to the GauthierFitness API are documented here.

Format inspired by [Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Before the `v1.0.0` tag, each entry
corresponds to a `GF{n}` feature branch merged into `main` (the project's branching convention), rather than a semantic
version number.

## [v1.0.10] - 2026-07-23

### Added

- Backfilled missing changelog entries for v1.0.7, v1.0.8, and v1.0.9.

## [v1.0.9] - 2026-07-23

### Fixed

- `tests/TestCase.php` now flushes the cache in `setUp()`: the rate limiter on `POST /api/payment/intent`
  (`throttle:3,1`) is cache-backed and previously leaked state between tests, causing intermittent 429s in
  `StripeIntentTest` depending on execution order. Reconfirmed stable across repeated runs: 200 tests, 524
  assertions.

## [v1.0.8] - 2026-07-23

### Fixed

- README Quick Start: added a `docker compose restart nginx` step after recreating the app container — nginx
  cached the old container's IP and caused 502s until restarted.
- `docker/Dockerfile` now actually `COPY`s `docker/php/php.ini` and `docker/php/www-pool-tuning.conf` into the
  image. They were never wired in before, leaving OPcache on its default `validate_timestamps=On`, which
  re-validates every file on every request over the slow Windows bind mount — adding ~10s per request. Caught
  while timing the delivery zip's payment flow end-to-end.

## [v1.0.7] - 2026-07-22

Brings together guest support for product customization and Stripe checkout, server-side shipping pricing, a
blocklist obfuscation fix, and a round of code-quality cleanup (GF33 to GF40).

### Added

- Guest customization sessions: unauthenticated users can create/own a `CustomProductSession` via an
  `X-Guest-Cart-Token` header instead of an account (`guest_token` column, `user_id` made nullable). AI design
  generation stays login-only.
- Guest checkout: `POST /api/payment/intent` no longer requires authentication — guest requests use the cart
  token instead, orders gained nullable `guest_token`/`email` columns, and order-confirmation emails route
  directly to the guest's address since there's no account to notify.
- `ShippingCalculator` service prices `standard` (4.90€, free above 70€ subtotal) and `express` (9.90€, never
  free) shipping server-side — the client-sent method is never trusted for the amount; `shipments` gained
  `method`/`cost` columns.
- Free-text product search via a `search` query param on `GET /api/products`.
- Leetspeak-aware normalization in the customization-text blocklist (e.g. `H!TLER` now matches `hitler`), plus
  a second moderation layer (`OpenAIModerationService::detectProhibitedVisualContent`) covering weapons/drugs/
  hate symbols that OpenAI's standard moderation categories miss.

### Fixed

- Customization/checkout endpoints now resolve the user explicitly via `$request->user('sanctum')` instead of
  the default guard — a regression where genuinely authenticated requests were silently treated as
  unauthenticated after guest support moved these routes out of the `auth:sanctum` middleware group.
- Removed a duplicated `/api/payment/intent` route registration; `OrderConfirmed`'s greeting no longer crashes
  for guest orders (falls back to the shipping firstname, then "client") since guest notifications have no
  `firstname` to read.
- `.env.docker.example` completed with previously-missing Mail/Stripe/OpenAI variables.
- README API route count corrected (43 → 44).

### Changed

- Refactor pass adding return-type declarations and trimming docblocks across controllers/models/services, no
  behavior change; `roles` gained a nullable `label` column.

## [v1.0.6] - 2026-07-13

### Fixed

- `README.md`: the Docker quickstart (recommended path) never mentioned `php artisan key:generate`, unlike the
  non-Docker path. `APP_KEY` therefore stayed empty in `.env.docker` — breaking everything that depends on encryption (
  cookies, sessions, CSRF) on a fresh install following the instructions to the letter. Caught while testing the
  delivery zip end-to-end (clean extraction + local build). Added key generation + reloading the container with
  `--force-recreate`.

## [v1.0.5] - 2026-07-13

### Changed

- Updated minor/patch Composer dependencies grouped by Dependabot (`dedoc/scramble` 0.13.28→0.13.33, `laravel/sail`) and
  the GitHub Actions used in CI/CD (`actions/checkout`, `actions/cache`, `actions/upload-artifact`, `docker/*`,
  `peter-evans/repository-dispatch`).

## [v1.0.4] - 2026-07-12

### Fixed

- Missing `DB_ROOT_PASSWORD` in `.env.docker.example`: `docker compose up` failed on a fresh clone/zip, MySQL refusing
  to initialize without a root password specified. Caught while testing the Docker startup on a fresh extraction of the
  delivery zip (see Incident Report 7 for a similar case).

## [v1.0.3] - 2026-07-12

### Changed

- Controllers reorganized by domain (`Cart/`, `Catalog/`, `Customization/`, `Orders/`, `Payments/`, `Support/`), same
  pattern as `Admin/`/`AI/`/`Auth/` already in place. `CartMergeService` moved to `Services/Cart/`.
- Scramble annotations (`scenario=`, `@queryParam`, `@urlParam`) translated to English to make the generated OpenAPI
  docs consistent.

## [v1.0.2] - 2026-07-10

### Fixed

- The `vendor/` built into the Docker image was masked by the local bind mount, breaking the app for anyone who had
  never run Composer natively — the container entrypoint now rebuilds it automatically at startup if needed.
- Added healthchecks (MySQL + app) and switched to `docker compose up -d --wait` to eliminate first-run race conditions.

## [v1.0.1] - 2026-07-10

### Fixed

- Added the missing `.env.docker.example` for local Docker initialization on a fresh clone/zip (see Incident Report 7).

### Removed

- Empty, unused favicon.ico.

## [v1.0.0] - 2026-07-08

First tagged release of the API. Brings together all features developed from GF0 to GF31: product catalog,
2D/3D configurator, cart, Stripe checkout, authentication, order/shipping/returns management,
admin back-office, AI design generation, legal pages, Swagger documentation.

### Added

- Pure unit tests (`tests/Unit/Services/...`) on isolated business logic: cart price snapshot calculation (
  `CartPricingCalculator`), FIFO stock lot allocation (`StockAllocator`), AI moderation threshold evaluation (
  `ModerationThresholdEvaluator`), banned term detection (`PromptBlocklist`).
- GitHub Issue template (`.github/ISSUE_TEMPLATE/bug_report.md`) to structure bug reporting.
- New nutrition products (isolates, whey) with associated visuals.

### Changed

- Extracted price calculation and stock allocation logic out of `StripeController`, with no behavior change (same rules,
  same results), to make it testable in isolation.

### Fixed

- Missing `OPENAI_API_KEY` in `.env.example`.
- Committed product images, fixed the storage folder's `.gitignore`.
- README: missing `php artisan storage:link` step in the local install instructions.

## [GF30 - V1GF Last Checkup] - 2026-07-05

### Fixed

- Final checks and fixes before the final V1.

## [GF29 - Lighthouse] - 2026-07-05

### Changed

- Performance and accessibility optimizations following Lighthouse audits (before/after fixes).

## [GF28 - V1GF Fixs (2)] - 2026-07-04

### Fixed

- Various fixes identified while preparing the final V1.

## [GF27 - Documentation V2] - 2026-07-02

### Changed

- Translated code comments and docblocks to English (no logic changed).

## [GF26 - Forgot Password] - 2026-07-01

### Added

- Forgot-password feature (`ForgotPasswordController`, `ResetPasswordController`) via Laravel's native broker, email
  notification, anti-enumeration, throttling, Sanctum token revocation on reset.

## [GF25 - V1GF Fixs] - 2026-07-01

### Fixed

- Pre-V1 fixes: AI configuration in production/staging environments, Stripe CI (per-branch public key), checkout
  shipping form memory.

## [GF15 - IA Generation] - 2026-06-28

### Added

- AI image generation (`gpt-image-1`) integrated into the 2D and 3D configurators, with 4-tier moderation (brand
  blocklist, OpenAI moderation + configurable threshold, native model refusal, image moderation before storage). Prompt
  and rejection history tracked (`prompt_histories`).

## [GF24 - Sentry] - 2026-06-26

### Added

- Backend Sentry integration (exception capture, traces, release tracking).

## [GF23 - Tests Strategy] - 2026-06-24

### Added

- Consolidated test strategy (PHPUnit Feature suite).

## [GF22 - Documentation] - 2026-06-22

### Added

- Technical project documentation.

## [GF21 - Swagger Doc] - 2026-06-18

### Added

- Generated API documentation (Scramble/Swagger).

## [GF20 - Shipments & Returns] - 2026-06-16

### Added

- Order shipping and returns management.

## [GF19 - Help Service] - 2026-06-16

### Added

- Help / contact service.

## [GF18 - Juridic] - 2026-06-15

### Added

- Legal pages and content (legal notices, T&Cs).

## [GF17 - Build Pipeline V2] - 2026-05-27

### Changed

- Improved the CI/CD pipeline.

## [GF16 - Build Pipeline V0/V1] - 2026-02-19 / 2026-05-11

### Added

- Set up the continuous integration and deployment pipeline (GitHub Actions, Docker image, GHCR registry).

## [GF14 - Panel Admin] - 2026-05-11

### Added

- Admin back-office (products, stock, orders, dashboard).

## [GF13 - Configuration 3D Produit V3] - 2026-05-03

## [GF12 - Configuration 3D Produit V2] - 2026-04-19

## [GF11 - Configuration 3D Produit V1] - 2026-04-17

### Added

- 3D product configurator (Three.js), successive versions.

## [GF10 - Configuration Produit V3] - 2026-04-16

## [GF9 - Configuration Produit V2] - 2026-04-11

## [GF8 - Configuration Produit V1] - 2026-04-07

### Added

- 2D product configurator (Konva), successive versions.

## [GF7 - App Stability] - 2026-03-26

### Fixed

- Application stability fixes.

## [GF6 - Orders Details] - 2026-02-15

### Added

- Order detail view.

## [GF5 - Orders Checkout] - 2026-02-03

### Added

- Order tunnel and Stripe payment.

## [GF4 - Users Authentification] - 2026-02-01

### Added

- User authentication (Sanctum).

## [GF3 - Product Cart] - 2026-01-22

### Added

- Product cart.

## [GF2 - Product Details] - 2026-01-18

### Added

- Detailed product page.

## [GF1 - Product Catalog] - 2026-01-18

### Added

- Initial product catalog.

## [GF0 - Project setup]

### Added

- Initial Laravel project setup.
