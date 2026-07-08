# Changelog

Toutes les évolutions notables de l'API GauthierFitness sont documentées ici.

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/). Avant le tag `v1.0.0`, chaque entrée correspond à une branche de fonctionnalité `GF{n}` fusionnée dans `main` (convention de branchage du projet), plutôt qu'à un numéro de version sémantique.

## [v1.0.0] - 2026-07-08

Première release taguée de l'API. Regroupe l'ensemble des fonctionnalités développées de GF0 à GF31 : catalogue
produit, configurateur 2D/3D, panier, checkout Stripe, authentification, gestion des commandes/livraisons/retours,
back-office admin, génération de designs par IA, pages légales, documentation Swagger.

### Added
- Tests unitaires purs (`tests/Unit/Services/...`) sur la logique métier isolée : calcul de prix snapshot du panier (`CartPricingCalculator`), allocation FIFO des lots de stock (`StockAllocator`), évaluation du seuil de modération IA (`ModerationThresholdEvaluator`), détection de termes interdits (`PromptBlocklist`).
- Template GitHub Issue (`.github/ISSUE_TEMPLATE/bug_report.md`) pour structurer la consignation des anomalies.
- Nouveaux produits nutrition (isolats, whey) avec visuels associés.

### Changed
- Extraction de la logique de calcul de prix et d'allocation de stock hors de `StripeController`, sans changement de comportement (mêmes règles, mêmes résultats), pour la rendre testable en isolation.

### Fixed
- `OPENAI_API_KEY` manquante dans `.env.example`.
- Images produits committées, `.gitignore` du dossier storage corrigé.
- README : étape `php artisan storage:link` manquante dans les instructions d'installation locale.

## [GF30 — V1GF Last Checkup] - 2026-07-05
### Fixed
- Dernières vérifications et correctifs avant la V1 finale.

## [GF29 — Lighthouse] - 2026-07-05
### Changed
- Optimisations de performance et d'accessibilité suite aux audits Lighthouse (avant/après correctifs).

## [GF28 — V1GF Fixs (2)] - 2026-07-04
### Fixed
- Corrections diverses identifiées lors de la préparation de la V1 finale.

## [GF27 — Documentation V2] - 2026-07-02
### Changed
- Traduction en anglais des commentaires et docblocks du code (aucune logique modifiée).

## [GF26 — Forgot Password] - 2026-07-01
### Added
- Fonctionnalité mot de passe oublié (`ForgotPasswordController`, `ResetPasswordController`) via le broker natif Laravel, notification par email, anti-enumeration, throttling, révocation des tokens Sanctum lors du reset.

## [GF25 — V1GF Fixs] - 2026-07-01
### Fixed
- Correctifs pré-V1 : configuration IA en environnement de production/staging, CI Stripe (clé publique par branche), mémoire du formulaire de livraison en checkout.

## [GF15 — IA Generation] - 2026-06-28
### Added
- Génération d'images par IA (`gpt-image-1`) intégrée aux configurateurs 2D et 3D, avec modération à 4 niveaux (blocklist marque, modération OpenAI + seuil configurable, refus natif du modèle, modération de l'image avant stockage). Historique des prompts et rejets tracé (`prompt_histories`).

## [GF24 — Sentry] - 2026-06-26
### Added
- Intégration Sentry backend (capture des exceptions, traces, release tracking).

## [GF23 — Tests Strategy] - 2026-06-24
### Added
- Stratégie de tests consolidée (suite PHPUnit Feature).

## [GF22 — Documentation] - 2026-06-22
### Added
- Documentation technique du projet.

## [GF21 — Swagger Doc] - 2026-06-18
### Added
- Documentation API générée (Scramble/Swagger).

## [GF20 — Shipments & Returns] - 2026-06-16
### Added
- Gestion des livraisons et retours de commande.

## [GF19 — Help Service] - 2026-06-16
### Added
- Service d'aide / contact.

## [GF18 — Juridic] - 2026-06-15
### Added
- Pages et contenus juridiques (mentions légales, CGV).

## [GF17 — Build Pipeline V2] - 2026-05-27
### Changed
- Amélioration du pipeline CI/CD.

## [GF16 — Build Pipeline V0/V1] - 2026-02-19 / 2026-05-11
### Added
- Mise en place du pipeline d'intégration et de déploiement continu (GitHub Actions, image Docker, registry GHCR).

## [GF14 — Panel Admin] - 2026-05-11
### Added
- Back-office admin (produits, stock, commandes, dashboard).

## [GF13 — Configuration 3D Produit V3] - 2026-05-03
## [GF12 — Configuration 3D Produit V2] - 2026-04-19
## [GF11 — Configuration 3D Produit V1] - 2026-04-17
### Added
- Configurateur de produit 3D (Three.js), versions successives.

## [GF10 — Configuration Produit V3] - 2026-04-16
## [GF9 — Configuration Produit V2] - 2026-04-11
## [GF8 — Configuration Produit V1] - 2026-04-07
### Added
- Configurateur de produit 2D (Konva), versions successives.

## [GF7 — App Stability] - 2026-03-26
### Fixed
- Corrections de stabilité applicative.

## [GF6 — Orders Details] - 2026-02-15
### Added
- Détail des commandes.

## [GF5 — Orders Checkout] - 2026-02-03
### Added
- Tunnel de commande et paiement Stripe.

## [GF4 — Users Authentification] - 2026-02-01
### Added
- Authentification utilisateur (Sanctum).

## [GF3 — Product Cart] - 2026-01-22
### Added
- Panier produit.

## [GF2 — Product Details] - 2026-01-18
### Added
- Fiche produit détaillée.

## [GF1 — Product Catalog] - 2026-01-18
### Added
- Catalogue produits initial.

## [GF0 — Project setup]
### Added
- Mise en place initiale du projet Laravel.
