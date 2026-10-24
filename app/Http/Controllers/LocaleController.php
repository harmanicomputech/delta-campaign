<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(SetLocale::supported())],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return redirect()
            ->to($this->safeReturnUrl($request))
            ->withCookie(cookie()->forever(SetLocale::COOKIE, $validated['locale'], httpOnly: true, sameSite: 'lax'));
    }

    /** Only redirect back to a same-origin page. */
    private function safeReturnUrl(Request $request): string
    {
        $previous = url()->previous();

        return str_starts_with($previous, $request->getSchemeAndHttpHost().'/') || $previous === $request->getSchemeAndHttpHost()
            ? $previous
            : route('home');
    }
}
