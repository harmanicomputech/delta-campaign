<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies browser security headers to every response and a nonce-based
 * Content Security Policy to HTML responses.
 *
 * Known relaxations (see docs/ARCHITECTURE.md §8):
 * - script-src allows 'unsafe-eval' because Alpine's standard build evaluates
 *   expressions with Function(). Livewire's CSP-safe build is evaluated in Phase 12.
 * - style-src-attr allows inline style attributes, which Livewire's DOM morphing sets.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set(
            'Permissions-Policy',
            'camera=(self), geolocation=(self), microphone=(), payment=(), usb=(), interest-cohort=()',
        );

        if (config('campaign.security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($this->wantsPolicy($request, $response)) {
            $headers->set('Content-Security-Policy', $this->policy((string) Vite::cspNonce()));
        }

        return $response;
    }

    private function wantsPolicy(Request $request, Response $response): bool
    {
        // Horizon ships its own inline assets and is restricted to operators.
        if ($request->is(trim((string) config('horizon.path'), '/').'*')) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function policy(string $nonce): string
    {
        $self = "'self'";
        $scripts = [$self, "'nonce-{$nonce}'", "'unsafe-eval'"];
        $styles = [$self, "'nonce-{$nonce}'"];
        $connect = [$self];

        // Vite dev server (local development only).
        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(Vite::hotFile()));
            $scripts[] = $hot;
            $styles[] = $hot;
            $connect[] = $hot;
            $connect[] = preg_replace('/^http/', 'ws', $hot);
        }

        $directives = [
            'default-src' => [$self],
            'script-src' => $scripts,
            'style-src' => $styles,
            'style-src-attr' => ["'unsafe-inline'"],
            'img-src' => [$self, 'data:', 'blob:'],
            'font-src' => [$self],
            'connect-src' => $connect,
            'media-src' => [$self, 'blob:'],
            'worker-src' => [$self],
            'manifest-src' => [$self],
            'frame-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'object-src' => ["'none'"],
            'base-uri' => [$self],
            'form-action' => [$self],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $name): string => $name.' '.implode(' ', array_unique($sources)))
            ->values()
            ->all();

        if ($reportUri = config('campaign.security.csp_report_uri')) {
            $policy[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $policy);
    }
}
