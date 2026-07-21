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
    // Register bindings in container
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret', env('STRIPE_SECRET')));
        });
    }

    // Bootstrap the app
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->configureScramble();
    }

    // Configure Scramble
    private function configureScramble(): void
    {
        $auth_middleware_patterns = ['auth', 'auth:*'];

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $open_api) {
                $open_api->secure(
                    SecurityScheme::http('bearer')
                        ->as('Sanctum')
                        ->setDescription('Sanctum token obtained via `POST /api/login` or `POST /api/register`. Prefixed with `Bearer ` in the `Authorization` header.')
                );
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) use ($auth_middleware_patterns) {
                $transformers->prepend(function (Operation $operation, RouteInfo $route_info) use ($auth_middleware_patterns): void {
                    $has_auth_middleware = collect($route_info->route->gatherMiddleware())->some(fn (string $mw) => Str::is($auth_middleware_patterns, $mw));
                    if (! $has_auth_middleware) {
                        $operation->security = [];
                    }
                });

                $transformers->append(function (Operation $operation, RouteInfo $route_info): void {
                    if ($route_info->route->uri() === 'api/ai/designs/generate') {
                        $this->documentAiDesignResponses($operation);
                    }
                });
            });
    }

    // Manually document responses of `POST /api/ai/designs/generate`
    private function documentAiDesignResponses(Operation $operation): void
    {
        // Success status 201
        $operation->responses = array_values(array_filter($operation->responses ?? [], fn ($response) => ! ($response instanceof Response && (int) $response->code === 200)));

        // 201 : design generated, moderated and persisted
        $asset = (new ObjectType)
            ->addProperty('id', new IntegerType)
            ->addProperty('type', (new StringType)->examples(['generated']))
            ->addProperty('path', (new StringType)->examples(['designs/fitness_design_652f0a1b9c8d4.png']))
            ->addProperty('mime_type', (new StringType)->examples(['image/png']))
            ->addProperty('is_primary', new BooleanType);

        $design = (new ObjectType)
            ->addProperty('id', new IntegerType)
            ->addProperty('name', (new StringType)->examples(['Generated design']))
            ->addProperty('status', (new StringType)->examples(['generated']))
            ->addProperty('prompt', new StringType)
            ->addProperty('provider', (new StringType)->examples(['openai']))
            ->addProperty('image_path', (new StringType)->examples(['designs/fitness_design_652f0a1b9c8d4.png']))
            ->addProperty('preview_url', (new StringType)->examples(['http://localhost:8000/storage/designs/fitness_design_652f0a1b9c8d4.png']))
            ->addProperty('assets', (new ArrayType)->setItems($asset));

        $success_body = (new ObjectType)
            ->addProperty('message', (new StringType)->examples(['Design generated successfully.']))
            ->addProperty('data', $design)
            ->setRequired(['message', 'data']);

        $operation->addResponse(
            Response::make(201)
                ->setDescription('Design generated, validated by moderation, and persisted.')
                ->setContent('application/json', Schema::fromType($success_body))
        );

        // 422 : content rejected by moderation
        $moderation_body = (new ObjectType)
            ->addProperty('message', (new StringType)->examples(['Votre demande ne respecte pas nos règles de contenu et ne peut pas être générée.']))
            ->addProperty('reason', (new StringType)
                ->enum(['prompt_blocked', 'prompt_flagged', 'image_provider_rejected', 'image_flagged'])
                ->setDescription('Origin of the rejection: brand blocklist, prompt moderation, image generator refusal, or generated image moderation.'))
            ->addProperty('categories', (new ArrayType)
                ->setItems(new StringType)
                ->setDescription('Triggered moderation categories (e.g. violence, sexual, hate).')
                ->examples([['violence']]))
            ->setRequired(['message']);

        $operation->addResponse(
            Response::make(422)
                ->setDescription(
                    'Request rejected. Possible cases: product not customizable or not allowing '.
                    'AI; prompt rejected by moderation (`reason: prompt_flagged`); '.
                    'generated image rejected by moderation (`reason: image_flagged`). '.
                    'Form validation errors also return a 422.'
                )
                ->setContent('application/json', Schema::fromType($moderation_body))
        );

        // 503 : AI provider unreachable or erroring
        $unavailable_body = (new ObjectType)
            ->addProperty('message', (new StringType)->examples(['Le service de génération IA est temporairement indisponible. Veuillez réessayer dans un instant.']))
            ->setRequired(['message']);

        $operation->addResponse(
            Response::make(503)
                ->setDescription('AI service (OpenAI) temporarily unavailable or unreachable.')
                ->setContent('application/json', Schema::fromType($unavailable_body))
        );
    }
}
