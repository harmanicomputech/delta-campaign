<?php

declare(strict_types=1);

namespace Tests\Feature\Offline;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class PwaTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    public function test_the_manifest_describes_an_installable_app(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(parse_url($icon['src'], PHP_URL_PATH)));
        }
    }

    public function test_the_service_worker_precaches_only_the_shell_and_never_pages(): void
    {
        $response = $this->get(route('pwa.sw'))->assertOk()->assertHeader('Service-Worker-Allowed', '/')->assertHeader('Cache-Control', 'no-cache, private');
        $script = (string) $response->getContent();

        $this->assertStringContainsString('"\/offline"', $script);
        $this->assertStringNotContainsString('/workspace', $script);
        $this->assertStringContainsString("request.mode === 'navigate'", $script);
    }

    public function test_the_offline_shell_is_public_and_holds_no_personal_data(): void
    {
        $this->get(route('pwa.offline'))->assertOk()->assertSee(__('sync.offline_title'))->assertDontSee('Sample');
    }

    public function test_pages_link_the_manifest(): void
    {
        $this->get(route('login'))->assertSee(route('pwa.manifest'), false);
    }

    public function test_low_data_mode_turns_off_background_polling(): void
    {
        $this->seedAccessControl();
        $user = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $this->signIn($user)->get(route('workspace.dashboard'))->assertSee('wire:poll.60s.visible', false);
        $this->signIn($user)->withUnencryptedCookie('low_data', '1')->get(route('workspace.dashboard'))->assertDontSee('wire:poll.60s.visible', false);
    }
}
