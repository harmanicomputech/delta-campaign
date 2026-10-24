<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\LoginEventType;
use App\Enums\OtpPurpose;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\LoginEvent;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Authentication\OtpService;
use App\Services\Authentication\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class LoginFlowTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private const string PHONE = '+2348031234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    private function coordinator(): User
    {
        return User::factory()->phone(self::PHONE)->withRole('lga_coordinator', $this->lga('udu')->id)->create();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee(__('auth.sign_in_title'))->assertSee('name="phone"', false);
    }

    public function test_workspace_requires_sign_in(): void
    {
        $this->get('/workspace')->assertRedirect('/login');
    }

    public function test_rejects_invalid_phone_formats(): void
    {
        $this->from('/login')->post('/login', ['phone' => '12345'])->assertRedirect('/login')->assertSessionHasErrors('phone');
    }

    public function test_full_sign_in_with_a_one_time_code(): void
    {
        $user = $this->coordinator();

        $this->post('/login', ['phone' => '0803 123 4567'])->assertRedirect('/login/verify');
        $code = $this->lastCodeSentTo(self::PHONE);
        $this->assertNotNull($code, 'A code is sent to registered, active numbers.');

        $this->get('/login/verify')->assertOk()->assertSee('+234 ••• ••• 4567');

        $response = $this->post('/login/verify', ['code' => $code]);

        $response->assertRedirect(route('workspace.dashboard'));
        $this->assertAuthenticatedAs($user);
        $response->assertCookie('device_id');
        $this->assertSame(1, Device::query()->where('user_id', $user->id)->count());
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::LoginSucceeded)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'auth.login')->where('actor_id', $user->id)->exists());
        $this->get('/workspace')->assertOk()->assertSee($user->name);
    }

    public function test_only_a_hash_of_the_code_is_stored(): void
    {
        $this->coordinator();
        $this->post('/login', ['phone' => self::PHONE]);
        $code = $this->lastCodeSentTo(self::PHONE);
        $challenge = OtpChallenge::query()->firstOrFail();

        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertSame(64, strlen($challenge->code_hash));
        $this->assertStringNotContainsString((string) $code, json_encode($challenge->getAttributes()));
    }

    public function test_responses_do_not_reveal_whether_a_number_is_registered(): void
    {
        $this->coordinator();

        $registered = $this->post('/login', ['phone' => self::PHONE]);
        $verifyRegistered = $this->get('/login/verify');
        auth()->logout();
        session()->flush();

        $unregistered = $this->post('/login', ['phone' => '+2348099999999']);
        $verifyUnregistered = $this->get('/login/verify');

        $this->assertSame($registered->getStatusCode(), $unregistered->getStatusCode());
        $this->assertSame($registered->headers->get('Location'), $unregistered->headers->get('Location'));
        // Compare the page content (Livewire injects its assets only once per test process).
        $normalise = fn (string $html): string => (string) preg_replace(['/\+234 ••• ••• \d{4}/u', '/name="_token" value="[^"]+"/'], '', \Illuminate\Support\Str::between($html, '<main', '</main>'));
        $this->assertSame($normalise($verifyRegistered->getContent()), $normalise($verifyUnregistered->getContent()));
        $this->assertSame(0, $this->messagesTo('+2348099999999'), 'Nothing is sent to unregistered numbers.');
    }

    public function test_wrong_codes_fail_and_the_challenge_locks_after_five_attempts(): void
    {
        $this->coordinator();
        $this->post('/login', ['phone' => self::PHONE]);
        $code = $this->lastCodeSentTo(self::PHONE);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/verify', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->post('/login/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertTrue(LoginEvent::query()->where('event', LoginEventType::ChallengeLocked)->exists());
    }

    public function test_expired_codes_are_rejected(): void
    {
        $this->coordinator();
        $this->post('/login', ['phone' => self::PHONE]);
        $code = $this->lastCodeSentTo(self::PHONE);

        $this->travel(6)->minutes();

        $this->post('/login/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_works_only_once(): void
    {
        $user = $this->coordinator();
        $service = app(OtpService::class);
        $challenge = $service->request(self::PHONE, OtpPurpose::Login, '127.0.0.1');
        $code = $this->lastCodeSentTo(self::PHONE);

        $this->assertTrue($service->verify($challenge, (string) $code));
        $this->assertFalse($service->verify($challenge->refresh(), (string) $code));
        $this->assertSame($user->id, $challenge->user_id);
    }

    public function test_suspended_accounts_receive_no_code_and_cannot_sign_in(): void
    {
        User::factory()->phone(self::PHONE)->suspended()->withRole('lga_coordinator', $this->lga('udu')->id)->create();

        $this->post('/login', ['phone' => self::PHONE])->assertRedirect('/login/verify');

        $this->assertSame(0, $this->messagesTo(self::PHONE));
    }

    public function test_codes_are_rate_limited_per_number(): void
    {
        $this->coordinator();
        $service = app(OtpService::class);

        for ($i = 0; $i < 7; $i++) {
            $service->request(self::PHONE, OtpPurpose::Login, '10.0.0.'.$i);
            $this->travel(61)->seconds();
        }

        $this->assertSame(5, $this->messagesTo(self::PHONE));
        $this->assertTrue(LoginEvent::query()->where('event', LoginEventType::CodeRequestThrottled)->exists());
    }

    public function test_resending_within_the_cooldown_sends_nothing_new(): void
    {
        $this->coordinator();
        $this->post('/login', ['phone' => self::PHONE]);
        $this->post('/login/resend')->assertRedirect('/login/verify');

        $this->assertSame(1, $this->messagesTo(self::PHONE));
    }

    public function test_the_session_is_regenerated_on_sign_in(): void
    {
        $this->coordinator();
        $this->post('/login', ['phone' => self::PHONE]);
        $before = session()->getId();

        $this->post('/login/verify', ['code' => $this->lastCodeSentTo(self::PHONE)]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_privileged_users_must_pass_the_second_factor(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->phone(self::PHONE)->withTwoFactor($secret)->withRole('dg')->create();

        $this->post('/login', ['phone' => self::PHONE]);
        $this->post('/login/verify', ['code' => $this->lastCodeSentTo(self::PHONE)])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post('/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor/challenge', ['code' => Totp::code($secret, intdiv(time(), 30))])->assertRedirect(route('workspace.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_codes_work_once(): void
    {
        User::factory()->phone(self::PHONE)->withTwoFactor()->withRole('dg')->create();

        foreach ([true, false] as $expected) {
            auth()->logout();
            $this->post('/login', ['phone' => self::PHONE]);
            $this->travel(61)->seconds();
            OtpChallenge::query()->update(['created_at' => now()->subMinutes(2)]);
            $this->post('/login', ['phone' => self::PHONE]);
            $this->post('/login/verify', ['code' => $this->lastCodeSentTo(self::PHONE)]);
            $this->post('/two-factor/challenge', ['code' => 'aaaa-bbbb']);

            $expected ? $this->assertAuthenticated() : $this->assertGuest();
        }
    }

    public function test_privileged_users_without_an_authenticator_must_enrol_first(): void
    {
        $user = User::factory()->phone(self::PHONE)->withRole('super_admin')->create();
        $this->post('/login', ['phone' => self::PHONE]);
        $this->post('/login/verify', ['code' => $this->lastCodeSentTo(self::PHONE)]);

        $this->get('/workspace')->assertRedirect(route('two-factor.setup'));
        $this->get('/two-factor/setup')->assertOk()->assertSee('otpauth:', false);

        $secret = (string) $user->refresh()->two_factor_secret;
        $this->post('/two-factor/setup', ['code' => Totp::code($secret, intdiv(time(), 30))])->assertRedirect(route('two-factor.setup'));
        $this->get('/two-factor/setup')->assertOk()->assertSee(__('auth.recovery_title'));

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->get('/workspace')->assertOk();
    }

    public function test_a_new_device_triggers_a_security_alert(): void
    {
        $user = $this->coordinator();
        Device::query()->create(['user_id' => $user->id, 'fingerprint_hash' => str_repeat('a', 64), 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->post('/login', ['phone' => self::PHONE]);
        $this->post('/login/verify', ['code' => $this->lastCodeSentTo(self::PHONE)]);

        $this->assertTrue(LoginEvent::query()->where('event', LoginEventType::NewDevice)->exists());
        $this->assertStringContainsString('New sign-in', collect(\App\Services\Sms\Drivers\OutboxSmsGateway::messages())->first()['message']);
    }

    public function test_signing_out_ends_the_session(): void
    {
        $user = $this->coordinator();
        $this->signIn($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertTrue(AuditLog::query()->where('action', 'auth.logout')->exists());
    }
}
