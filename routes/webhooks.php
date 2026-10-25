<?php

use App\Http\Controllers\Webhooks\InboundSmsController;
use App\Http\Controllers\Webhooks\UssdController;
use App\Http\Middleware\VerifyWebhookSecret;
use Illuminate\Support\Facades\Route;

/*
| Provider callbacks. No session, cookies or CSRF; each URL carries the shared
| secret (?key=…) and is rate limited. Responses must stay fast.
*/
Route::middleware([VerifyWebhookSecret::class, 'throttle:webhooks'])->prefix('webhooks')->name('webhooks.')->group(function (): void {
    Route::post('/ussd', UssdController::class)->name('ussd');
    Route::post('/sms/inbound', InboundSmsController::class)->name('sms.inbound');
});
