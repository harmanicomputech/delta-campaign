<?php

use App\Http\Controllers\Webhooks\InboundSmsController;
use App\Http\Controllers\Webhooks\SmsDeliveryController;
use App\Http\Controllers\Webhooks\UssdController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Middleware\VerifyWebhookSecret;
use App\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Support\Facades\Route;

/*
| Provider callbacks. No session, cookies or CSRF; each URL carries the shared
| secret (?key=…) and is rate limited. Responses must stay fast.
*/
Route::middleware([VerifyWebhookSecret::class, 'throttle:webhooks'])->prefix('webhooks')->name('webhooks.')->group(function (): void {
    Route::post('/ussd', UssdController::class)->name('ussd');
    Route::post('/sms/inbound', InboundSmsController::class)->name('sms.inbound');
    Route::post('/sms/delivery', SmsDeliveryController::class)->name('sms.delivery');
});

// WhatsApp Cloud API: signed with the app secret instead of a URL key.
Route::middleware([VerifyWhatsAppSignature::class, 'throttle:webhooks'])->prefix('webhooks')->name('webhooks.')->group(function (): void {
    Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
    Route::post('/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp');
});
