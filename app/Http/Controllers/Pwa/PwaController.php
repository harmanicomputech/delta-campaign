<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Installable web app: manifest, service worker and the offline shell.
 *
 * The service worker caches only the offline shell and static build assets.
 * Signed-in pages and API responses are never cached, so personal data never
 * lands in the browser's HTTP cache; field data for offline use is kept by the
 * app itself in IndexedDB, encrypted (resources/js/offline).
 */
final class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'short_name' => mb_substr((string) config('app.name'), 0, 12),
            'description' => __('pwa.description'),
            'id' => '/workspace',
            'start_url' => '/workspace?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f6f7f6',
            'theme_color' => '#052418',
            'lang' => app()->getLocale(),
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('icons/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('nav.tasks'), 'url' => '/workspace/tasks'],
                ['name' => __('nav.canvassing'), 'url' => '/workspace/canvassing'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function serviceWorker(): Response
    {
        $assets = $this->buildAssets();
        $version = substr(hash('sha256', json_encode($assets).filemtime(__FILE__)), 0, 12);

        $script = view('pwa.service-worker', [
            'version' => $version,
            'precache' => array_values(array_unique(['/offline', '/favicon.svg', '/icons/icon-192.png', ...$assets])),
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): View
    {
        return view('pages.offline');
    }

    /** @return list<string> Built CSS/JS/font files from Vite's manifest. */
    private function buildAssets(): array
    {
        $path = public_path('build/manifest.json');

        if (! is_file($path)) {
            return [];
        }

        $files = [];
        foreach ((array) json_decode((string) file_get_contents($path), true) as $chunk) {
            foreach (array_merge([$chunk['file'] ?? null], $chunk['css'] ?? [], $chunk['assets'] ?? []) as $file) {
                if (is_string($file) && preg_match('/\.(js|css|woff2)$/', $file) === 1) {
                    $files[] = '/build/'.$file;
                }
            }
        }

        return $files;
    }
}
