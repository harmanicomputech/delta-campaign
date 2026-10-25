<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnforceIdleLock;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\RequirePinConfirmation;
use App\Models\User;
use App\Services\Authentication\DeviceAwareSessionHandler;
use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\Drivers\AfricasTalkingSmsGateway;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use App\Services\Ussd\Contracts\UssdProvider;
use App\Services\Ussd\Providers\AfricasTalkingUssdProvider;
use App\Services\Ussd\UssdSessionStore;
use App\Support\Crypto\Keys;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, fn (Application $app): SmsGateway => match (config('campaign.sms.driver')) {
            'africastalking' => new AfricasTalkingSmsGateway(
                (string) config('services.africastalking.username'),
                (string) config('services.africastalking.api_key'),
                config('services.africastalking.sender_id'),
                (int) config('services.africastalking.timeout'),
            ),
            default => new OutboxSmsGateway,
        });

        $this->app->bind(UssdProvider::class, AfricasTalkingUssdProvider::class);
        $this->app->singleton(UssdSessionStore::class, fn (): UssdSessionStore => new UssdSessionStore('ussd'));
    }

    public function boot(): void
    {
        $this->guardProductionConfiguration();

        // Catch lazy loading (N+1), silently discarded attributes and missing attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Session::extend('database', fn (Application $app) => new DeviceAwareSessionHandler(
            $app['db']->connection(config('session.connection')),
            (string) config('session.table'),
            (int) config('session.lifetime'),
            $app,
        ));

        // Re-apply workspace protections to Livewire component requests made from those pages.
        Livewire::addPersistentMiddleware([EnsureTwoFactorEnrolled::class, EnforceIdleLock::class, RequirePinConfirmation::class]);

        $this->configureAuthorization();
        $this->configureTrustedProxies();
        $this->configureRateLimiting();
    }

    private function guardProductionConfiguration(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        if (config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }

        // Throws with a clear message when a key is missing or malformed.
        Keys::phoneHash();
        Keys::fieldEncryption();

        if (config('campaign.sms.driver') === 'outbox') {
            throw new RuntimeException('SMS_DRIVER=outbox cannot be used in production.');
        }
    }

    /**
     * Permission slugs (config/permissions.php) are abilities: Gate::allows('supporters.view').
     * Suspended accounts are denied everything. Other abilities fall through to policies.
     */
    private function configureAuthorization(): void
    {
        $known = [];
        foreach ((array) config('permissions.permissions') as $group) {
            foreach (array_keys($group) as $slug) {
                $known[$slug] = true;
            }
        }

        Gate::before(function (User $user, string $ability) use ($known): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            return isset($known[$ability]) ? $user->hasPermission($ability) : null;
        });
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
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('verify', fn (Request $request): array => [
            Limit::perMinute(10)->by('verify-ip:'.$request->ip()),
            Limit::perMinute(6)->by('verify-session:'.$request->session()->getId()),
        ]);
        // Offline sync batches: generous for field use, bounded per person.
        // Provider callbacks (USSD target: 200 requests/second across the gateway's addresses).
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(12000)->by($request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('sensitive', fn (Request $request): Limit => Limit::perMinute(30)->by('sensitive:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
