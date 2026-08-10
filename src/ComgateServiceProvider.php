<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sloukapetr\ComgateLaravelPackage\Http\Controllers\ComgateWebhookController;

use function config;
use function config_path;

class ComgateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/comgate.php', 'comgate');

        $this->app->singleton(ComgateService::class, fn (): ComgateService => new ComgateService());
        $this->app->alias(ComgateService::class, 'comgate');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/comgate.php' => config_path('comgate.php'),
        ], 'comgate-config');

        if ((bool) config('comgate.webhook.enabled', true)) {
            Route::middleware((array) config('comgate.webhook.middleware', ['api']))
                ->post((string) config('comgate.webhook.path', '/comgate/webhook'), ComgateWebhookController::class)
                ->name((string) config('comgate.webhook.route_name', 'comgate.webhook'));
        }
    }
}
