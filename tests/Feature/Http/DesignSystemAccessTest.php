<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DesignSystemAccessTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'reference' => ['/design-system'],
            'shell' => ['/design-system/shell'],
            'sign in' => ['/design-system/auth/sign-in'],
            'verify' => ['/design-system/auth/verify'],
            'error preview' => ['/design-system/errors/419'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_render_when_enabled(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    #[DataProvider('pages')]
    public function test_pages_are_hidden_when_disabled(string $uri): void
    {
        config(['campaign.design_system_enabled' => false]);

        $this->get($uri)->assertNotFound();
    }

    #[DataProvider('pages')]
    public function test_pages_are_never_served_in_production(string $uri): void
    {
        $this->app['env'] = 'production';

        $this->get($uri)->assertNotFound();
    }

    public function test_unknown_error_previews_are_not_routable(): void
    {
        $this->get('/design-system/errors/418')->assertNotFound();
    }

    public function test_shell_never_shows_invented_metrics(): void
    {
        $this->get('/design-system/shell')
            ->assertOk()
            ->assertSee('Registered supporters')
            ->assertSee('—')
            ->assertSee(__('shell.preview_banner'));
    }

    public function test_sample_figures_are_marked_sample(): void
    {
        $this->get('/design-system')->assertSee(__('ui.sample'))->assertSee('Provisional brand');
    }
}
