<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavigationItem;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class NavigationTest extends TestCase
{
    public function test_items_for_unbuilt_modules_have_no_link(): void
    {
        $items = collect(app(Navigation::class)->groups())->flatMap(fn (array $group): array => $group['items']);

        $planned = $items->filter(fn (NavigationItem $item): bool => $item->isPlanned());
        $this->assertNotEmpty($planned);
        $this->assertTrue($planned->every(fn (NavigationItem $item): bool => $item->url === null && $item->phase > 1));
    }

    public function test_every_configured_route_exists(): void
    {
        foreach (config('navigation') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['route'] !== null) {
                    $this->assertTrue(Route::has($item['route']), "Missing route {$item['route']}");
                }
            }
        }
    }

    public function test_every_configured_icon_exists(): void
    {
        foreach (config('navigation') as $group) {
            foreach ($group['items'] as $item) {
                $this->assertFileExists(resource_path("icons/{$item['icon']}.svg"));
            }
        }
    }

    public function test_command_palette_only_offers_built_destinations(): void
    {
        $urls = array_column(app(Navigation::class)->paletteItems(), 'url');

        $this->assertContains(route('workspace.dashboard'), $urls);
        $this->assertNotContains('', $urls);
    }

    public function test_sidebar_marks_the_current_page(): void
    {
        $this->get('/design-system/shell')->assertSee('aria-current="page"', false);
    }
}
