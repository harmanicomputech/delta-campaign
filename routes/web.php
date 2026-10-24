<?php

use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\LocaleController;
use App\Http\Middleware\EnsureDesignSystemEnabled;
use Illuminate\Support\Facades\Route;

/*
| Public website (full site: Phase 10)
*/
Route::view('/', 'pages.public.home')->name('home');

Route::post('/locale', LocaleController::class)
    ->middleware('throttle:20,1')
    ->name('locale.update');

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
        Route::get('/errors/{status}', [DesignSystemController::class, 'error'])
            ->whereIn('status', ['403', '404', '419', '429', '500', '503'])
            ->name('error');
    });
