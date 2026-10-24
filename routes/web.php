<?php

use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ReauthenticationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Pwa\PwaController;
use App\Http\Controllers\Workspace\DashboardController;
use App\Http\Controllers\Workspace\ExportDownloadController;
use App\Http\Controllers\Workspace\ImportFilesController;
use App\Http\Controllers\Workspace\TaskEvidenceController;
use App\Http\Middleware\EnsureDesignSystemEnabled;
use App\Livewire\Workspace\Account\SecurityPage;
use App\Livewire\Workspace\Approvals\ApprovalsInbox;
use App\Livewire\Workspace\Audit\AuditLogIndex;
use App\Livewire\Workspace\Canvassing\WalkListShow;
use App\Livewire\Workspace\Canvassing\WalkListsIndex;
use App\Livewire\Workspace\CommandCentre\CommandCentre;
use App\Livewire\Workspace\Exports\ExportsIndex;
use App\Livewire\Workspace\Geography\GeographyIndex;
use App\Livewire\Workspace\Geography\ImportShow;
use App\Livewire\Workspace\Geography\ImportsIndex;
use App\Livewire\Workspace\Notifications\NotificationsIndex;
use App\Livewire\Workspace\Privacy\PrivacyCentre;
use App\Livewire\Workspace\Privacy\RequestShow;
use App\Livewire\Workspace\Settings\SettingsPage;
use App\Livewire\Workspace\Supporters\SupporterShow;
use App\Livewire\Workspace\Supporters\SupportersIndex;
use App\Livewire\Workspace\Tasks\TaskShow;
use App\Livewire\Workspace\Tasks\TasksIndex;
use App\Livewire\Workspace\Training\TrainingIndex;
use App\Livewire\Workspace\Training\TrainingModuleShow;
use App\Livewire\Workspace\Users\UserShow;
use App\Livewire\Workspace\Users\UsersIndex;
use App\Livewire\Workspace\Volunteers\VolunteerShow;
use App\Livewire\Workspace\Volunteers\VolunteersIndex;
use Illuminate\Support\Facades\Route;

/*
| Public website (full site: Phase 10)
*/
Route::view('/', 'pages.public.home')->name('home');

/*
| Installable app: manifest, service worker and offline shell (no personal data)
*/
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

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

    Route::get('/command-centre', CommandCentre::class)->middleware('can:command-centre.view')->name('command-centre');
    Route::get('/approvals', ApprovalsInbox::class)->middleware('can:approvals.view')->name('approvals');
    Route::get('/settings', SettingsPage::class)->middleware(['can:settings.manage', 'pin.confirm'])->name('settings');

    Route::get('/geography', GeographyIndex::class)->middleware('can:geography.view')->name('geography');
    Route::middleware('can:geography.import')->prefix('geography/imports')->name('geography.')->group(function (): void {
        Route::get('/', ImportsIndex::class)->name('imports');
        Route::get('/template.csv', [ImportFilesController::class, 'template'])->name('imports.template');
        Route::get('/{import}', ImportShow::class)->name('imports.show');
        Route::get('/{import}/issues.csv', [ImportFilesController::class, 'report'])->middleware('throttle:sensitive')->name('imports.report');
    });

    Route::get('/users', UsersIndex::class)->middleware('can:users.view')->name('users.index');
    Route::get('/users/{user:uuid}', UserShow::class)->middleware('can:users.view')->name('users.show');

    Route::get('/audit', AuditLogIndex::class)->middleware(['can:audit.view', 'pin.confirm'])->name('audit.index');

    Route::get('/supporters', SupportersIndex::class)->middleware('can:supporters.view')->name('supporters.index');
    Route::get('/supporters/{supporter:uuid}', SupporterShow::class)->middleware('can:supporters.view')->name('supporters.show');

    // Field operations. Components check "view in scope OR assigned to me" themselves.
    Route::get('/volunteers', VolunteersIndex::class)->middleware('can:volunteers.view')->name('volunteers.index');
    Route::get('/volunteers/{volunteer:uuid}', VolunteerShow::class)->middleware('can:volunteers.view')->name('volunteers.show');
    Route::get('/training', TrainingIndex::class)->name('training.index');
    Route::get('/training/{module}', TrainingModuleShow::class)->name('training.show');
    Route::get('/canvassing', WalkListsIndex::class)->name('canvassing.index');
    Route::get('/canvassing/{list}', WalkListShow::class)->name('canvassing.show');
    Route::get('/tasks', TasksIndex::class)->name('tasks.index');
    Route::get('/tasks/{task}', TaskShow::class)->name('tasks.show');
    Route::get('/tasks/{task}/evidence/{evidence:uuid}', TaskEvidenceController::class)->middleware('throttle:sensitive')->name('tasks.evidence');
    Route::get('/notifications', NotificationsIndex::class)->name('notifications');

    Route::get('/exports', ExportsIndex::class)->name('exports.index');
    Route::get('/exports/{export}/download', ExportDownloadController::class)->middleware(['signed', 'pin.confirm', 'throttle:sensitive'])->name('exports.download');

    Route::get('/privacy', PrivacyCentre::class)->middleware('pin.confirm')->name('privacy.index');
    Route::get('/privacy/requests/{request:uuid}', RequestShow::class)->middleware(['can:dsr.manage', 'pin.confirm'])->name('privacy.requests.show');
});

/*
| Field-app sync API (same-origin session + CSRF; JSON)
*/
Route::middleware(['workspace', 'throttle:sync'])->prefix('api/v1')->name('api.v1.')->group(function (): void {
    Route::post('/sync', [SyncController::class, 'store'])->name('sync');
    Route::get('/sync/walk-lists', [SyncController::class, 'walkLists'])->name('sync.walk-lists');
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
