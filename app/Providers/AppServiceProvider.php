<?php

namespace App\Providers;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
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
            });
    }
}
