<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ReauthenticationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Workspace\DashboardController;
use App\Http\Controllers\Workspace\ExportDownloadController;
use App\Http\Middleware\EnsureDesignSystemEnabled;
use App\Livewire\Workspace\Account\SecurityPage;
use App\Livewire\Workspace\Audit\AuditLogIndex;
use App\Livewire\Workspace\Exports\ExportsIndex;
use App\Livewire\Workspace\Privacy\PrivacyCentre;
use App\Livewire\Workspace\Privacy\RequestShow;
use App\Livewire\Workspace\Supporters\SupporterShow;
use App\Livewire\Workspace\Supporters\SupportersIndex;
use App\Livewire\Workspace\Users\UserShow;
use App\Livewire\Workspace\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

/*
| Public website (full site: Phase 10)
*/
Route::view('/', 'pages.public.home')->name('home');

Route::post('/locale', LocaleController::class)
    ->middleware('throttle:20,1')
    ->name('locale.update');

/*
| Authentication: phone → one-time code → second factor (privileged roles)
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/login/verify', [LoginController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/verify', [LoginController::class, 'verify'])->middleware('throttle:verify')->name('login.verify.store');
    Route::post('/login/resend', [LoginController::class, 'resend'])->middleware('throttle:login')->name('login.resend');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware('throttle:verify')->name('two-factor.challenge.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->middleware('idle-lock')->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirmSetup'])->middleware(['idle-lock', 'throttle:verify'])->name('two-factor.setup.store');
    Route::get('/unlock', [ReauthenticationController::class, 'showUnlock'])->name('unlock');
    Route::get('/confirm-pin', [ReauthenticationController::class, 'showConfirm'])->middleware('idle-lock')->name('pin.confirm');
    Route::post('/reauthenticate', [ReauthenticationController::class, 'submit'])->middleware('throttle:verify')->name('reauthenticate');
    Route::post('/reauthenticate/code', [ReauthenticationController::class, 'sendCode'])->middleware('throttle:login')->name('reauthenticate.code');
});

/*
| Workspace (signed in, second factor where required, idle lock)
*/
Route::middleware('workspace')->prefix('workspace')->name('workspace.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/account', SecurityPage::class)->name('account');

    Route::get('/users', UsersIndex::class)->middleware('can:users.view')->name('users.index');
    Route::get('/users/{user:uuid}', UserShow::class)->middleware('can:users.view')->name('users.show');

    Route::get('/audit', AuditLogIndex::class)->middleware(['can:audit.view', 'pin.confirm'])->name('audit.index');

    Route::get('/supporters', SupportersIndex::class)->middleware('can:supporters.view')->name('supporters.index');
    Route::get('/supporters/{supporter:uuid}', SupporterShow::class)->middleware('can:supporters.view')->name('supporters.show');

    Route::get('/exports', ExportsIndex::class)->name('exports.index');
    Route::get('/exports/{export}/download', ExportDownloadController::class)->middleware(['signed', 'pin.confirm', 'throttle:sensitive'])->name('exports.download');

    Route::get('/privacy', PrivacyCentre::class)->middleware('pin.confirm')->name('privacy.index');
    Route::get('/privacy/requests/{request:uuid}', RequestShow::class)->middleware(['can:dsr.manage', 'pin.confirm'])->name('privacy.requests.show');
});

/*
| Design-system reference and shell previews (never served in production)
*/
Route::middleware(EnsureDesignSystemEnabled::class)
    ->prefix('design-system')
    ->name('design-system.')
    ->group(function (): void {
        Route::get('/', [DesignSystemController::class, 'index'])->name('index');
        Route::get('/shell', [DesignSystemController::class, 'shell'])->name('shell');
        Route::get('/auth/sign-in', [DesignSystemController::class, 'signIn'])->name('sign-in');
        Route::get('/auth/verify', [DesignSystemController::class, 'verify'])->name('verify');
        Route::get('/outbox', [DesignSystemController::class, 'outbox'])->name('outbox');
        Route::get('/errors/{status}', [DesignSystemController::class, 'error'])
            ->whereIn('status', ['403', '404', '419', '429', '500', '503'])
            ->name('error');
    });
