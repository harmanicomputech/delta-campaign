<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\LoginEventType;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Authentication\PinService;
use App\Services\Authentication\SignInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class SessionSecurityTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->user = User::factory()->withRole('lga_coordinator', $this->lga('sapele')->id)->create();
        app(PinService::class)->set($this->user, '4826');
    }

    public function test_a_suspended_account_is_signed_out_on_its_next_request(): void
    {
        $this->signIn($this->user)->get('/workspace')->assertOk();

        $this->user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->get('/workspace')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_revoked_device_ends_its_sessions(): void
    {
        $device = Device::query()->create(['user_id' => $this->user->id, 'fingerprint_hash' => str_repeat('b', 64), 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $this->signIn($this->user)->withSession([SignInService::SESSION_DEVICE => $device->id])->get('/workspace')->assertOk();

        app(SignInService::class)->revokeDevice($device);

        $this->get('/workspace')->assertRedirect('/login');
    }

    public function test_idle_sessions_lock_and_unlock_with_the_pin(): void
    {
        $this->signIn($this->user)->get('/workspace')->assertOk();

        $this->travel(31)->minutes();

        $this->get('/workspace/account')->assertRedirect('/unlock');
        $this->get('/unlock')->assertOk()->assertSee(__('auth.unlock_title'));
        $this->post('/reauthenticate', ['mode' => 'unlock', 'pin' => '0000'])->assertSessionHasErrors('pin');
        $this->post('/reauthenticate', ['mode' => 'unlock', 'pin' => '4826'])->assertRedirect(route('workspace.account'));
        $this->get('/workspace')->assertOk();
        $this->assertTrue(LoginEvent::query()->where('event', LoginEventType::Unlocked)->exists());
    }

    public function test_locked_sessions_reject_background_livewire_requests(): void
    {
        $this->signIn($this->user)->withSession([SignInService::SESSION_LOCKED => true])
            ->get('/workspace', ['X-Livewire' => 'true'])
            ->assertStatus(419);
    }

    public function test_idle_lock_can_be_cleared_with_a_one_time_code_instead_of_the_pin(): void
    {
        $this->signIn($this->user)->withSession([SignInService::SESSION_LOCKED => true]);

        $this->post('/reauthenticate/code')->assertRedirect();
        $code = $this->lastCodeSentTo((string) $this->user->phone);
        $this->assertNotNull($code);

        $this->post('/reauthenticate', ['mode' => 'unlock', 'code' => $code])->assertRedirect();
        $this->get('/workspace')->assertOk();
    }

    public function test_the_pin_locks_after_repeated_failures(): void
    {
        $pins = app(PinService::class);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($pins->verify($this->user, '1111'));
        }

        $this->assertTrue($pins->isLocked($this->user->refresh()));
        $this->assertFalse($pins->verify($this->user, '4826'), 'Even the correct PIN fails while locked.');
        $this->travel(16)->minutes();
        $this->assertTrue($pins->verify($this->user->refresh(), '4826'));
    }

    public function test_weak_pins_are_refused_and_pins_are_hashed(): void
    {
        foreach (['1234', '0000', '987654', '12', 'abcd'] as $pin) {
            $this->assertNotNull(PinService::weakness($pin), $pin);
        }

        $this->assertNull(PinService::weakness('4826'));
        $this->assertStringStartsWith('$2y$', (string) $this->user->refresh()->pin_hash);
    }

    public function test_sensitive_pages_require_a_recent_pin_confirmation(): void
    {
        $dpo = User::factory()->withTwoFactor()->withRole('data_protection_officer')->create();
        app(PinService::class)->set($dpo, '4826');

        $this->signIn($dpo)->get('/workspace/audit')->assertOk();

        $this->travel(16)->minutes();
        $this->withSession([SignInService::SESSION_LAST_ACTIVITY => now()->getTimestamp()]);

        $this->get('/workspace/audit')->assertRedirect('/confirm-pin');
        $this->post('/reauthenticate', ['mode' => 'confirm', 'pin' => '4826'])->assertRedirect(route('workspace.audit.index'));
        $this->get('/workspace/audit')->assertOk();
    }

    public function test_sessions_can_be_listed_and_revoked(): void
    {
        foreach (['s-current', 's-other-1', 's-other-2'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        }
        $service = app(SignInService::class);

        $this->assertCount(3, $service->sessions($this->user));
        $this->assertSame(2, $service->revokeAllSessions($this->user, 's-current'));
        $this->assertSame(['s-current'], DB::table('sessions')->where('user_id', $this->user->id)->pluck('id')->all());
    }

    public function test_account_page_shows_security_settings(): void
    {
        $this->signIn($this->user)->get('/workspace/account')
            ->assertOk()
            ->assertSee(__('account.pin_title'))
            ->assertSee(__('account.sessions'))
            ->assertSee($this->user->maskedPhone());
    }
}
