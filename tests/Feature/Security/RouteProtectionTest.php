<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyWebhookSecret;
use App\Http\Middleware\VerifyWhatsAppSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\View\ComponentAttributeBag;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

/**
 * Guards against routes added without protection: every workspace and API
 * route requires a signed-in session, every webhook a shared secret or
 * signature, and nothing private is reachable anonymously.
 */
final class RouteProtectionTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    /** @return list<Route> */
    private function routes(callable $filter): array
    {
        return array_values(array_filter(Router::getRoutes()->getRoutes(), $filter));
    }

    public function test_every_workspace_and_api_route_requires_the_workspace_stack(): void
    {
        $routes = $this->routes(fn (Route $r) => str_starts_with($r->uri(), 'workspace') || str_starts_with($r->uri(), 'api/'));
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('workspace', $route->gatherMiddleware(), "Route {$route->uri()} is missing the workspace middleware");
        }
    }

    public function test_every_webhook_requires_a_secret_or_signature(): void
    {
        $routes = $this->routes(fn (Route $r) => str_starts_with($r->uri(), 'webhooks/'));
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(in_array(VerifyWebhookSecret::class, $middleware, true) || in_array(VerifyWhatsAppSignature::class, $middleware, true)
                || collect($middleware)->contains(fn ($m) => is_string($m) && (str_starts_with($m, 'webhook') || str_contains($m, 'VerifyWebhook') || str_contains($m, 'VerifyWhatsApp'))),
                "Webhook {$route->uri()} has no secret or signature check");
        }
    }

    public function test_guests_are_sent_to_sign_in_from_every_parameterless_workspace_page(): void
    {
        $this->seedAccessControl();
        foreach ($this->routes(fn (Route $r) => str_starts_with($r->uri(), 'workspace') && in_array('GET', $r->methods(), true) && ! str_contains($r->uri(), '{')) as $route) {
            $response = $this->get('/'.$route->uri());
            $this->assertTrue($response->isRedirect(route('login')), "Guest reached /{$route->uri()} ({$response->status()})");
        }
    }

    public function test_a_volunteer_cannot_open_privileged_pages(): void
    {
        $this->seedAccessControl();
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $privileged = ['workspace.users.index', 'workspace.audit.index', 'workspace.supporters.index', 'workspace.settings', 'workspace.geography.imports', 'workspace.command-centre',
            'workspace.messaging.index', 'workspace.content.index', 'workspace.rapid-response.index', 'workspace.contact.index', 'workspace.election.hub', 'workspace.election.results',
            'workspace.election.incidents.index', 'workspace.election.packs', 'workspace.election.setup', 'workspace.ussd-simulator', 'workspace.approvals'];

        foreach ($privileged as $name) {
            $status = $this->signIn($volunteer)->get(route($name))->status();
            $this->assertContains($status, [302, 403], "Volunteer got {$status} on {$name}");
            if ($status === 302) {
                $this->assertStringNotContainsString('/workspace/', (string) $this->signIn($volunteer)->get(route($name))->headers->get('Location'));
            }
        }
    }

    public function test_markdown_content_cannot_inject_scripts_or_javascript_links(): void
    {
        $html = view('components.public.markdown', ['text' => "<script>alert(1)</script>\n\n[x](javascript:alert(1)) <img src=x onerror=alert(1)>", 'attributes' => new ComponentAttributeBag])->render();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_session_cookie_is_http_only_and_same_site(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }
}
