<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Authentication\SignInService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sensitive routes re-ask for the PIN (or a one-time code) after the confirmation window. */
final class RequirePinConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = (int) $request->session()->get(SignInService::SESSION_PIN_CONFIRMED, 0);

        if (now()->getTimestamp() - $confirmedAt <= (int) config('campaign.auth.pin_confirm_minutes') * 60) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return response()->json(['message' => 'Confirm your PIN to continue.', 'confirm_url' => route('pin.confirm')], 423);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('pin.confirm');
    }
}
