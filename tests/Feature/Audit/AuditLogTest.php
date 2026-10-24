<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Livewire\Workspace\Audit\AuditLogIndex;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private AuditLogger $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->audit = app(AuditLogger::class);
    }

    public function test_entries_form_a_verifiable_hash_chain(): void
    {
        $first = $this->audit->record('test.first', context: ['amount' => 1.5, 'nested' => ['b' => 2, 'a' => 'ü']]);
        $second = $this->audit->record('test.second');

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
        $this->artisan('audit:verify')->assertSuccessful();
    }

    public function test_entries_cannot_be_updated_deleted_or_truncated(): void
    {
        $entry = $this->audit->record('test.immutable');

        // Each attempt in its own savepoint: a rejected statement aborts the enclosing transaction.
        $this->assertThrows(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'x'])), QueryException::class, 'append-only');
        $this->assertThrows(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->delete()), QueryException::class, 'append-only');
        $this->assertThrows(fn () => DB::transaction(fn () => DB::statement('TRUNCATE audit_logs')), QueryException::class, 'append-only');
        $this->assertSame('test.immutable', AuditLog::query()->findOrFail($entry->id)->action);
    }

    public function test_tampering_by_a_superuser_is_detected(): void
    {
        $this->audit->record('test.one');
        $victim = $this->audit->record('test.two', context: ['value' => 'original']);
        $this->audit->record('test.three');

        DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_no_update');
        DB::table('audit_logs')->where('id', $victim->id)->update(['context' => json_encode(['value' => 'forged'])]);
        DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_no_update');

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertFalse($result['valid']);
        $this->assertSame($victim->id, $result['broken_id']);
        $this->artisan('audit:verify')->assertFailed();
    }

    public function test_removed_entries_break_the_chain(): void
    {
        $this->audit->record('test.one');
        $gone = $this->audit->record('test.two');
        $this->audit->record('test.three');

        DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_no_update');
        DB::table('audit_logs')->where('id', $gone->id)->delete();
        DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_no_update');

        $this->assertStringContainsString('removed', (string) app(AuditChainVerifier::class)->verify()['reason']);
    }

    public function test_sensitive_values_never_reach_the_log(): void
    {
        $user = User::factory()->phone('+2348031239999')->create();
        $user->forceFill(['pin_hash' => 'secret-hash', 'name' => 'Renamed'])->save();

        $entry = $this->audit->recordChanges('user.updated', $user, ['note' => 'called 08031239999']);

        $json = json_encode([$entry->changes, $entry->context]);
        $this->assertStringContainsString('Renamed', $json);
        $this->assertStringNotContainsString('secret-hash', $json);
        $this->assertStringNotContainsString('8031239999', $json);
    }

    public function test_entries_record_actor_request_and_subject(): void
    {
        $user = User::factory()->withRole('super_admin')->create();
        $this->actingAs($user);

        $entry = $this->audit->record('test.subject', $user);

        $this->assertSame($user->id, $entry->actor_id);
        $this->assertSame((string) $user->id, $entry->subject_id);
        $this->assertNotNull($entry->uuid);
    }

    public function test_checkpoint_writes_the_latest_hash(): void
    {
        $entry = $this->audit->record('test.checkpoint');

        $this->artisan('audit:checkpoint')->expectsOutputToContain($entry->hash)->assertSuccessful();
    }

    public function test_audit_page_requires_permission_and_verifies_the_chain(): void
    {
        $dpo = User::factory()->withTwoFactor()->withRole('data_protection_officer')->create();
        $coordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $this->audit->record('test.visible');

        $this->signIn($coordinator)->get('/workspace/audit')->assertForbidden();

        $this->signIn($dpo)->get('/workspace/audit')->assertOk()->assertSee('test.visible');
        $this->assertTrue(AuditLog::query()->where('action', 'audit.viewed')->where('actor_id', $dpo->id)->exists());

        Livewire::test(AuditLogIndex::class)
            ->call('verifyChain')
            ->assertSet('verification.valid', true);
    }
}
