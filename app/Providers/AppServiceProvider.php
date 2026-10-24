<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->guardProductionConfiguration();

        // Catch lazy loading (N+1), silently discarded attributes and missing attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configureTrustedProxies();
        $this->configureRateLimiting();
    }

    private function guardProductionConfiguration(): void
    {
        if ($this->app->isProduction() && config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }
    }

    private function configureTrustedProxies(): void
    {
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) config('campaign.trusted_proxies')))));

        if ($proxies !== []) {
            TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('health', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
    }
}
