<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Africa's Talking callbacks are not signed, so callback URLs carry a shared
 * secret (?key=…) compared in constant time. Without a configured secret the
 * webhooks are closed.
 */
final class VerifyWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.africastalking.webhook_secret');
        $given = (string) $request->query('key', '');

        if ($secret === '' || ! hash_equals($secret, $given)) {
            abort(403);
        }

        return $next($request);
    }
}
