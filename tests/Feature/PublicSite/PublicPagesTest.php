<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Models\ContentItem;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class PublicPagesTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return array_combine($p = ['/', '/about', '/manifesto', '/projects', '/lgas', '/lgas/udu', '/news', '/fact-checks', '/events', '/join', '/volunteer', '/polling-unit', '/contact', '/privacy', '/preferences'], array_map(fn ($x) => [$x], $p));
    }

    #[DataProvider('pages')]
    public function test_public_pages_render_with_seo_metadata(string $path): void
    {
        $this->get($path)->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_pages_without_approved_content_show_placeholders_not_invented_text(): void
    {
        $this->get('/about')->assertOk()->assertSee(__('public.not_published_title'));
        $this->get('/')->assertSee('[PLACEHOLDER', false);
    }

    public function test_sitemap_robots_and_feed(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->assertSee(route('public.projects.index'), false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /'); // Non-production sites are never indexed.
        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /workspace')->assertSee('Disallow: /events/ticket/')->assertSee('Sitemap: '.route('public.sitemap'), false);
        $this->app['env'] = 'testing';
        $this->get('/news/feed.xml')->assertOk();
    }

    public function test_pidgin_visitors_get_pidgin_navigation(): void
    {
        $this->withCookie('locale', 'pcm')->withSession(['locale' => 'pcm'])->get('/events')->assertOk()->assertSee(__('public.nav_contact', [], 'pcm'));
    }

    public function test_sample_seeder_marks_everything_as_sample_and_keeps_biography_placeholders_unpublished(): void
    {
        $this->seed(SampleDataSeeder::class);

        $this->assertSame(0, ContentItem::query()->where('is_sample', false)->count());
        $this->assertFalse(ContentItem::query()->where('slug', 'about')->firstOrFail()->isLive());
        $this->get('/news')->assertSee('SAMPLE');
        $this->get('/projects')->assertSee('SAMPLE');
    }
}
