<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceIdleLock;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\RequirePinConfirmation;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Readiness probe: outside the "web" group so probes never create sessions.
            Route::get('/health', HealthController::class)
                ->middleware('throttle:health')
                ->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        // Low-data preference is set by the browser itself and holds no secret.
        $middleware->encryptCookies(except: ['low_data']);
        $middleware->web(append: [SetLocale::class, EnsureAccountIsActive::class]);

        $middleware->alias([
            'two-factor' => EnsureTwoFactorEnrolled::class,
            'idle-lock' => EnforceIdleLock::class,
            'pin.confirm' => RequirePinConfirmation::class,
        ]);

        // The workspace: signed in, second factor enrolled where required, and not idle-locked.
        $middleware->group('workspace', ['web', 'auth', 'two-factor', 'idle-lock']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('workspace.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'health') || $request->expectsJson(),
        );

        // Never log request input that may carry phone numbers, OTPs or PINs.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'otp', 'code', 'pin', 'phone']);
    })->create();
