<?php

namespace App\Providers;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Stripe client via le container pour pouvoir le swap par un mock en tests.
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret', env('STRIPE_SECRET')));
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->configureScramble();
    }

    /**
     * Configure Scramble programmatically (here rather than in config/scramble.php)
     * because SecurityScheme objects are not var_export-serializable, which would
     * break `php artisan config:cache` in production.
     */
    private function configureScramble(): void
    {
        $authMiddlewarePatterns = ['auth', 'auth:*'];

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                        ->as('Sanctum')
                        ->setDescription('Token Sanctum obtenu via `POST /api/login` ou `POST /api/register`. Préfixé par `Bearer ` dans le header `Authorization`.')
                );
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) use ($authMiddlewarePatterns) {
                $transformers->prepend(function (Operation $operation, RouteInfo $routeInfo) use ($authMiddlewarePatterns): void {
                    $hasAuthMiddleware = collect($routeInfo->route->gatherMiddleware())
                        ->some(fn (string $mw) => Str::is($authMiddlewarePatterns, $mw));

                    if (! $hasAuthMiddleware) {
                        $operation->security = [];
                    }
                });

                // Scramble n'infère pas finement les réponses de la génération IA
                // (succès en 201, et les rejets de modération en 422) : on les
                // documente explicitement ici.
                $transformers->append(function (Operation $operation, RouteInfo $routeInfo): void {
                    if ($routeInfo->route->uri() === 'api/ai/designs/generate') {
                        $this->documentAiDesignResponses($operation);
                    }
                });
            });
    }

    /**
     * Documente explicitement les réponses de `POST /api/ai/designs/generate` :
     * le succès 201 (design persisté) et le 422 de modération (prompt ou image
     * rejetés). Le 200 inféré par défaut est retiré au profit du 201 réel.
     */
    private function documentAiDesignResponses(Operation $operation): void
    {
        // Retire le 200 inféré : le succès réel est un 201.
        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            fn ($response) => ! ($response instanceof Response && (int) $response->code === 200)
        ));

        // ── 201 : design généré, modéré et persisté ──────────────────────────
        $asset = (new ObjectType)
            ->addProperty('id', new IntegerType)
            ->addProperty('type', (new StringType)->example('generated'))
            ->addProperty('path', (new StringType)->example('designs/fitness_design_652f0a1b9c8d4.png'))
            ->addProperty('mime_type', (new StringType)->example('image/png'))
            ->addProperty('is_primary', new BooleanType);

        $design = (new ObjectType)
            ->addProperty('id', new IntegerType)
            ->addProperty('name', (new StringType)->example('Generated design'))
            ->addProperty('status', (new StringType)->example('generated'))
            ->addProperty('prompt', new StringType)
            ->addProperty('provider', (new StringType)->example('openai'))
            ->addProperty('image_path', (new StringType)->example('designs/fitness_design_652f0a1b9c8d4.png'))
            ->addProperty('preview_url', (new StringType)->example('http://localhost:8000/storage/designs/fitness_design_652f0a1b9c8d4.png'))
            ->addProperty('assets', (new ArrayType)->setItems($asset));

        $successBody = (new ObjectType)
            ->addProperty('message', (new StringType)->example('Design generated successfully.'))
            ->addProperty('data', $design)
            ->setRequired(['message', 'data']);

        $operation->addResponse(
            Response::make(201)
                ->setDescription('Design généré, validé par la modération et persisté.')
                ->setContent('application/json', Schema::fromType($successBody))
        );

        // ── 422 : contenu rejeté par la modération (écrase la réf générique) ──
        $moderationBody = (new ObjectType)
            ->addProperty('message', (new StringType)->example('Votre demande ne respecte pas nos règles de contenu et ne peut pas être générée.'))
            ->addProperty('reason', (new StringType)
                ->enum(['prompt_blocked', 'prompt_flagged', 'image_provider_rejected', 'image_flagged'])
                ->setDescription('Origine du rejet : blocklist de marque, modération du prompt, refus du générateur d’images, ou modération de l’image générée.'))
            ->addProperty('categories', (new ArrayType)
                ->setItems(new StringType)
                ->setDescription('Catégories de modération déclenchées (ex. violence, sexual, hate).')
                ->example(['violence']))
            ->setRequired(['message']);

        $operation->addResponse(
            Response::make(422)
                ->setDescription(
                    "Requête rejetée. Cas possibles : produit non customisable ou n'autorisant ".
                    "pas l'IA ; prompt rejeté par la modération (`reason: prompt_flagged`) ; ".
                    'image générée rejetée par la modération (`reason: image_flagged`). '.
                    'Les erreurs de validation du formulaire renvoient également un 422.'
                )
                ->setContent('application/json', Schema::fromType($moderationBody))
        );

        // ── 503 : fournisseur IA injoignable ou en erreur (timeout, 429, 5xx) ──
        $unavailableBody = (new ObjectType)
            ->addProperty('message', (new StringType)->example('Le service de génération IA est temporairement indisponible. Veuillez réessayer dans un instant.'))
            ->setRequired(['message']);

        $operation->addResponse(
            Response::make(503)
                ->setDescription('Service IA (OpenAI) momentanément indisponible ou injoignable.')
                ->setContent('application/json', Schema::fromType($unavailableBody))
        );
    }
}
