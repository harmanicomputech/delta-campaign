<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Meta signs webhook bodies with the app secret (X-Hub-Signature-256: sha256=…). */
final class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            return $next($request); // Subscription verification uses the verify token instead.
        }

        $secret = (string) config('services.whatsapp.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
            abort(403);
        }

        return $next($request);
    }
}
