<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Internal reference pages for the component library and application shells.
 * Guarded by EnsureDesignSystemEnabled; unavailable in production.
 */
final class DesignSystemController extends Controller
{
    public function index(): View
    {
        return view('pages.design-system.index');
    }

    public function shell(): View
    {
        return view('pages.design-system.shell', [
            'phases' => $this->roadmap(),
        ]);
    }

    public function signIn(): View
    {
        return view('pages.design-system.sign-in');
    }

    public function verify(): View
    {
        return view('pages.design-system.verify');
    }

    public function error(string $status): View
    {
        return view("errors.{$status}", ['exception' => new HttpException((int) $status)]);
    }

    /**
     * Module delivery status, derived from config/navigation.php.
     *
     * @return array<int, list<array{label: string, icon: string, available: bool}>>
     */
    private function roadmap(): array
    {
        $phases = [];

        foreach ((array) config('navigation') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['route'] === 'design-system.index') {
                    continue;
                }

                $phases[(int) $item['phase']][] = [
                    'label' => __($item['label']),
                    'icon' => $item['icon'],
                    'available' => $item['route'] !== null,
                ];
            }
        }

        ksort($phases);

        return $phases;
    }
}
