# GauthierFitness - Backend

> API REST de la boutique e-commerce GauthierFitness. Gère le catalogue, le panier, les paiements Stripe, les sessions
> de customisation 3D, la génération de designs IA et l'administration.

Repo : `CharlesGAUTHIER1999/gauthierfitness-backend` &nbsp;·&nbsp; Production : <https://api.gauthierfitness.fr>

> Documentation projet transverse (architecture, déploiement, manuel utilisateur, mise à jour) : [meta-repo
`gauthierfitness/docs`](https://github.com/CharlesGAUTHIER1999/gauthierfitness/tree/main/docs)

---

## Stack

| Couche          | Technologie                                              |
|-----------------|----------------------------------------------------------|
| Runtime         | PHP 8.3                                                  |
| Framework       | Laravel 13                                               |
| Base de données | MySQL 8                                                  |
| Auth API        | Laravel Sanctum (Bearer token)                           |
| Paiement        | Stripe (PaymentIntents + webhook signé)                  |
| IA              | OpenAI Images                                            |
| Doc API         | [Scramble](https://scramble.dedoc.co) (OpenAPI 3.1 auto) |
| Tests           | PHPUnit 11 (SQLite in-memory en CI)                      |
| Style           | Laravel Pint (PSR-12)                                    |
| CI/CD           | GitHub Actions → image GHCR → dispatch infra             |

---

## Démarrage local

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link   # requis pour que les images produits soient servies

# Lance simultanément server, queue, pail, vite
composer dev
```

API exposée sur `http://localhost:8000`, doc Swagger sur `http://localhost:8000/docs/api`.

### Variables d'environnement clés

| Var                                       | Usage                                               |
|-------------------------------------------|-----------------------------------------------------|
| `APP_ENV`                                 | `local` / `staging` / `production`                  |
| `DB_*`                                    | Connexion MySQL                                     |
| `SANCTUM_STATEFUL_DOMAINS`                | Domaines autorisés à utiliser le cookie Sanctum     |
| `CORS_ALLOWED_ORIGINS`                    | Origines front autorisées (vide = localhost en dev) |
| `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe                                              |
| `OPENAI_API_KEY`                          | OpenAI Images                                       |
| `MAIL_*`                                  | SMTP (Mailpit en local, OVH en prod)                |
| `API_VERSION`                             | Version exposée dans la spec OpenAPI                |

Tableau
complet → [docs/02-deployment.md § 4](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/02-deployment.md#4-variables-denvironnement).

### Docker (optionnel)

Deux `Dockerfile` distincts, pour deux usages différents :

| Fichier               | Usage                                                                           |
|-----------------------|---------------------------------------------------------------------------------|
| `Dockerfile` (racine) | Multi-stage, buildé par la CI (`target: production`) → image publiée sur GHCR   |
| `docker/Dockerfile`   | Mono-stage, utilisé par `docker-compose.yml` pour un environnement de dev local |

```bash
cp .env.example .env    # si pas déjà fait
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

API accessible sur `http://localhost:8000`, MySQL sur `localhost:3308` (port mappé pour éviter un conflit avec un MySQL local).

---

## Structure du code

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/        Admin produits, commandes, stock
│   │   ├── AI/           AIDesignController (génération IA)
│   │   ├── Auth/         Login/Register/Logout/Verification
│   │   ├── CartController.php
│   │   ├── CustomizationController.php
│   │   ├── CustomizationAssetController.php
│   │   ├── OrderController.php
│   │   ├── ProductController.php
│   │   ├── ContactController.php
│   │   └── StripeController.php          ← PaymentIntent + webhook
│   ├── Middleware/       AdminMiddleware (garde les routes /admin)
│   ├── Requests/         FormRequests (validation + autorisation)
│   └── Resources/        API Resources (ProductResource)
├── Models/               Eloquent : User, Cart, Order, Product, StockLot, …
├── Notifications/        Emails transactionnels (OrderConfirmed, OrderStatusUpdated)
├── Providers/            AppServiceProvider (RateLimiter, Scramble security)
└── Services/
    ├── AI/               OpenAIImageService, OpenAIModerationService, ModerationThresholdEvaluator, PromptBlocklist
    ├── Pricing/           CartPricingCalculator
    ├── Stock/             StockAllocator
    └── CartMergeService.php

routes/api.php            43 routes (3 groupes : public / auth:sanctum / auth:sanctum + admin)
database/migrations/      Schéma versionné
swagger/openapi.json      Spec OpenAPI 3.1 régénérée par Scramble
tests/                    Feature + Unit (SQLite in-memory)
```

---

## Conventions

- **Validation** : `Illuminate\Http\Request::validate()` pour les règles simples, FormRequest dédié quand validation
  croisée nécessaire (cf. `AddToCartRequest::withValidator`).
- **Réponses** : `JsonResponse` typé en retour. API Resources pour les structures complexes.
- **Doc OpenAPI** : Scramble lit les type-hints, FormRequests et docblocks. Pour grouper les endpoints, attribut PHP 8
  `#[Group(name: 'Authentification', weight: 1)]` sur la classe contrôleur. Pour les réponses :
  `@response 200 scenario="..." {...}` en docblock.
- **Sécurité Scramble** : la configuration du scheme Sanctum global + flagging public/privé est faite dans [
  `app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php), **pas** dans `config/scramble.php`. Les
  objets `SecurityScheme` ne sont pas `var_export`-sérialisables, ce qui ferait crasher `php artisan config:cache` en
  production.
- **Snapshot des prix** : `cart_items` et `order_items` portent le prix au moment de l'action, jamais relus du produit.
- **Idempotence webhooks** : table `webhook_events` indexée `(provider, provider_event_id)`.

---

## Tests et qualité

```bash
php artisan test                  # PHPUnit (SQLite in-memory en CI)
./vendor/bin/pint                 # Format PSR-12 (auto-fix)
./vendor/bin/pint --test          # Vérification sans fix (CI)
```

CI : `phpunit` → `lint` → `build image GHCR` → `dispatch infra`. Cf. [
`.github/workflows/ci-cd.yml`](.github/workflows/ci-cd.yml).

---

## Documentation OpenAPI

La documentation est générée **depuis le code** par Scramble - il n'y a rien à maintenir à la main pour la structure des
endpoints.

```bash
# Régénérer la spec
php artisan scramble:export       # → swagger/openapi.json

# Consulter en local
php artisan serve
# → http://localhost:8000/docs/api
```

Annotations supportées sur les contrôleurs : `#[Group(name, weight)]`, docblock summary, `@response`, `@queryParam`,
`@urlParam`, `@unauthenticated`.
Détail : [docs/05-api.md](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/05-api.md).

---

## Convention de branchage

- `feature` : `GF{n}-{NomCourt}` (ex : `GF21-SwaggerDoc`, `GF22-Documentation`)
- `develop` : push automatique → image `ghcr.io/.../gauthierfitness-backend:develop` → infra déploie staging
- `main` : push → image `:latest` + tag SHA → déclenchement manuel prod

---

## Liens utiles

- 📖 [Manuel de déploiement](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/02-deployment.md)
- 📖 [Manuel de mise à jour](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/04-upgrade.md)
- 📖 [Architecture détaillée](https://github.com/CharlesGAUTHIER1999/gauthierfitness/blob/main/docs/01-architecture.md)
- 🎨 [Repo frontend](https://github.com/CharlesGAUTHIER1999/gauthierfitness-frontend)
- 🚀 [Repo infra](https://github.com/CharlesGAUTHIER1999/gauthierfitness-infra)
