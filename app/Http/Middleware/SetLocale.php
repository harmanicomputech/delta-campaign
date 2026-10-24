<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the interface language: explicit session choice, then the
 * long-lived "locale" cookie, then the browser's Accept-Language, then the default.
 */
final class SetLocale
{
    public const string COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $supported = self::supported();

        foreach ([$request->session()->get('locale'), $request->cookie(self::COOKIE)] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        $preferred = $request->getPreferredLanguage($supported);

        return in_array($preferred, $supported, true) ? $preferred : (string) config('app.locale');
    }

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return array_values(array_intersect(
            (array) config('campaign.supported_locales'),
            array_keys((array) config('campaign.locales')),
        ));
    }
}
