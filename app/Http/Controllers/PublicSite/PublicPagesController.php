<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Enums\ContentType;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\CampaignEvent;
use App\Models\ContentItem;
use App\Models\EventRegistration;
use App\Models\Lga;
use App\Models\PrivacyNotice;
use App\Models\Project;
use App\Models\SenatorialDistrict;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Read-only public pages. Everything shown is published content, published
 * project records or public events; drafts never appear here.
 */
final class PublicPagesController extends Controller
{
    public function home(): View
    {
        return view('pages.public.home', [
            'news' => ContentItem::query()->published()->where('type', ContentType::News)->with('cover')->latest('published_at')->limit(3)->get(),
            'events' => CampaignEvent::query()->public()->where('status', EventStatus::Published)->where('starts_at', '>', now())->with('lga:id,name')->orderBy('starts_at')->limit(3)->get(),
            'projects' => Project::query()->published()->with(['lga:id,name', 'cover'])->latest('published_at')->limit(3)->get(),
            'factChecks' => ContentItem::query()->published()->where('type', ContentType::FactCheck)->latest('published_at')->limit(2)->get(),
        ]);
    }

    public function page(string $slug): View
    {
        $page = ContentItem::query()->published()->where('type', ContentType::Page)->where('slug', $slug)->with('cover')->first();

        return view('pages.public.page', ['page' => $page, 'slug' => $slug]);
    }

    public function about(): View
    {
        return $this->page('about');
    }

    public function manifesto(): View
    {
        return $this->page('manifesto');
    }

    public function privacy(): View
    {
        return view('pages.public.privacy', ['notice' => PrivacyNotice::current()]);
    }

    public function projects(Request $request): View
    {
        $lga = $request->string('lga')->toString();
        $category = $request->string('category')->toString();

        return view('pages.public.projects', [
            'projects' => Project::query()->published()->with(['lga:id,name,slug', 'cover'])
                ->when($lga !== '', fn ($q) => $q->whereHas('lga', fn ($l) => $l->where('slug', $lga)))
                ->when(in_array($category, Project::CATEGORIES, true), fn ($q) => $q->where('category', $category))
                ->latest('published_at')->paginate(12)->withQueryString(),
            'lgas' => Lga::query()->orderBy('name')->pluck('name', 'slug'),
            'lga' => $lga,
            'category' => $category,
        ]);
    }

    public function project(string $slug): View
    {
        $project = Project::query()->published()->where('slug', $slug)->with(['lga:id,name,slug', 'ward:id,name', 'cover'])->firstOrFail();

        return view('pages.public.project', ['project' => $project]);
    }

    public function lgas(): View
    {
        return view('pages.public.lgas', [
            'districts' => SenatorialDistrict::query()->with(['lgas' => fn ($q) => $q->orderBy('name')->withCount(['wards', 'pollingUnits'])])->orderBy('name')->get(),
            'projectCounts' => Project::query()->published()->toBase()->selectRaw('lga_id, count(*) as n')->groupBy('lga_id')->pluck('n', 'lga_id'),
        ]);
    }

    public function lga(string $slug): View
    {
        $lga = Lga::query()->where('slug', $slug)->with('senatorialDistrict')->withCount(['wards', 'pollingUnits'])->firstOrFail();

        return view('pages.public.lga', [
            'lga' => $lga,
            'page' => ContentItem::query()->published()->where('type', ContentType::Page)->where('lga_id', $lga->id)->first(),
            'projects' => Project::query()->published()->where('lga_id', $lga->id)->with('cover')->latest('published_at')->limit(6)->get(),
            'events' => CampaignEvent::query()->public()->where('status', EventStatus::Published)->where('lga_id', $lga->id)->where('starts_at', '>', now())->orderBy('starts_at')->limit(4)->get(),
        ]);
    }

    public function articles(ContentType $type): View
    {
        return view('pages.public.articles', [
            'type' => $type,
            'items' => ContentItem::query()->published()->where('type', $type)->with('cover')->latest('published_at')->paginate(12),
        ]);
    }

    public function news(): View
    {
        return $this->articles(ContentType::News);
    }

    public function factChecks(): View
    {
        return $this->articles(ContentType::FactCheck);
    }

    public function article(ContentType $type, string $slug): View
    {
        $item = ContentItem::query()->published()->where('type', $type)->where('slug', $slug)->with('cover')->firstOrFail();

        return view('pages.public.article', [
            'item' => $item,
            'more' => ContentItem::query()->published()->where('type', $type)->whereKeyNot($item->id)->latest('published_at')->limit(3)->get(),
        ]);
    }

    public function newsItem(string $slug): View
    {
        return $this->article(ContentType::News, $slug);
    }

    public function factCheck(string $slug): View
    {
        return $this->article(ContentType::FactCheck, $slug);
    }

    public function events(Request $request): View
    {
        $past = $request->boolean('past');

        return view('pages.public.events', [
            'events' => CampaignEvent::query()->public()->with('lga:id,name')
                ->when($past, fn ($q) => $q->where('starts_at', '<', now())->latest('starts_at'), fn ($q) => $q->where('starts_at', '>=', now()->subHours(6))->orderBy('starts_at'))
                ->paginate(12)->withQueryString(),
            'past' => $past,
        ]);
    }

    public function event(string $slug): View
    {
        return view('pages.public.event', ['event' => CampaignEvent::query()->public()->where('slug', $slug)->with(['lga:id,name,slug', 'cover'])->firstOrFail()]);
    }

    /** Ticket page: the opaque token is the only thing in the QR code. */
    public function ticket(string $token): Response
    {
        $registration = EventRegistration::query()->where('ticket_token', $token)->with('event')->firstOrFail();

        return response()->view('pages.public.ticket', ['registration' => $registration, 'event' => $registration->event])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    public function newsFeed(): Response
    {
        $items = ContentItem::query()->published()->whereIn('type', [ContentType::News, ContentType::FactCheck])->latest('published_at')->limit(20)->get();

        return response()->view('pages.public.feed', ['items' => $items], 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
    }

    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0'],
            ...collect(['public.about', 'public.manifesto', 'public.projects.index', 'public.news.index', 'public.events.index', 'public.fact-checks.index', 'public.lgas.index', 'public.join', 'public.volunteer', 'public.polling-unit', 'public.contact', 'public.privacy', 'public.preferences'])
                ->map(fn (string $r) => ['loc' => route($r), 'priority' => '0.8']),
        ])
            ->concat(ContentItem::query()->published()->whereIn('type', [ContentType::News, ContentType::FactCheck])->get(['type', 'slug', 'updated_at'])
                ->map(fn (ContentItem $c) => ['loc' => (string) $c->publicUrl(), 'lastmod' => $c->updated_at->toAtomString(), 'priority' => '0.6']))
            ->concat(Project::query()->published()->get(['slug', 'updated_at'])->map(fn (Project $p) => ['loc' => route('public.projects.show', $p->slug), 'lastmod' => $p->updated_at->toAtomString(), 'priority' => '0.6']))
            ->concat(CampaignEvent::query()->public()->get(['slug', 'updated_at'])->map(fn (CampaignEvent $e) => ['loc' => route('public.events.show', $e->slug), 'lastmod' => $e->updated_at->toAtomString(), 'priority' => '0.5']))
            ->concat(Lga::query()->get(['slug'])->map(fn (Lga $l) => ['loc' => route('public.lgas.show', $l->slug), 'priority' => '0.5']));

        return response()->view('pages.public.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /workspace', 'Disallow: /login', 'Disallow: /api/', 'Disallow: /webhooks/', 'Disallow: /events/ticket/', 'Sitemap: '.route('public.sitemap')]
            : ['User-agent: *', 'Disallow: /']; // Never index development or staging sites.

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
