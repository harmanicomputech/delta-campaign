<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Builds the workspace navigation from config/navigation.php, showing only
 * items the signed-in user is permitted to use. (Signed-out design-system
 * previews show every item.)
 */
final class Navigation
{
    public function __construct(private readonly Request $request) {}

    /**
     * @return list<array{label: string, items: list<NavigationItem>}>
     */
    public function groups(): array
    {
        $groups = [];

        $user = $this->request->user();

        foreach ((array) config('navigation', []) as $group) {
            $visible = array_filter($group['items'] ?? [], fn (array $item): bool => (($item['production'] ?? true) || ! app()->isProduction())
                && ($user === null || ($item['permission'] ?? null) === null || $user->canAny((array) $item['permission'])));
            $items = array_values(array_map(fn (array $item): NavigationItem => $this->item($item), $visible));

            if ($items !== []) {
                $groups[] = ['label' => __($group['label']), 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Items pinned to the mobile bottom bar.
     *
     * @return list<NavigationItem>
     */
    public function mobile(): array
    {
        $items = [];

        foreach ($this->groups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item->mobile) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Built destinations for the command palette.
     *
     * @return list<array{label: string, group: string, icon: string, url: string}>
     */
    public function paletteItems(): array
    {
        $items = [];

        foreach ($this->groups() as $group) {
            foreach ($group['items'] as $item) {
                if (! $item->isPlanned()) {
                    $items[] = ['label' => $item->label, 'group' => $group['label'], 'icon' => $item->icon, 'url' => (string) $item->url];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function item(array $item): NavigationItem
    {
        $route = $item['route'] ?? null;
        $exists = is_string($route) && Route::has($route);

        return new NavigationItem(
            label: __((string) $item['label']),
            icon: (string) $item['icon'],
            url: $exists ? route($route) : null,
            // An index route also stays highlighted on its detail pages (workspace.tasks.index → workspace.tasks.*).
            // 'also' lists further route patterns that belong to the same section.
            active: $exists && $this->request->routeIs($route, ...(str_ends_with($route, '.index') ? [substr($route, 0, -5).'*'] : []), ...(array) ($item['also'] ?? [])),
            phase: (int) ($item['phase'] ?? 0),
            mobile: (bool) ($item['mobile'] ?? false),
        );
    }
}
