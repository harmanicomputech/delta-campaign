<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Enums\ContentType;
use App\Models\ContentItem;
use App\Models\PollingUnit;
use App\Models\PrivacyNotice;
use App\Models\User;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pre-launch checks: configuration, secrets (presence only, never values),
 * content that must replace placeholders, sample data, administrators and
 * system health. Blockers must pass before going live; warnings need a
 * person to confirm them.
 */
final class LaunchReadiness
{
    /** @return list<array{group: string, name: string, ok: bool, blocker: bool, detail: string}> */
    public function run(): array
    {
        $checks = [];
        $add = function (string $group, string $name, bool $ok, string $detail, bool $blocker = true) use (&$checks): void {
            $checks[] = compact('group', 'name', 'ok', 'blocker', 'detail');
        };

        // Application
        $add('app', 'APP_ENV is production', app()->isProduction(), 'APP_ENV='.app()->environment());
        $add('app', 'Debug mode off', ! config('app.debug'), config('app.debug') ? 'APP_DEBUG is true' : 'off');
        $add('app', 'APP_URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'));
        $add('app', 'Application key set', filled(config('app.key')), filled(config('app.key')) ? 'set' : 'missing');
        $add('app', 'Design-system pages disabled', ! config('campaign.design_system_enabled'), config('campaign.design_system_enabled') ? 'DESIGN_SYSTEM_ENABLED=true' : 'disabled');
        $add('app', 'Structured (JSON) logging', in_array(config('logging.default'), ['json', 'stderr_json'], true) || str_contains((string) json_encode(config('logging.channels.'.config('logging.default'))), 'JsonFormatter'), 'LOG_CHANNEL='.config('logging.default'), blocker: false);

        // Transport and sessions
        $add('security', 'Secure session cookie', (bool) config('session.secure'), config('session.secure') ? 'on' : 'SESSION_SECURE_COOKIE is off');
        $add('security', 'HSTS enabled', (bool) config('campaign.security.hsts'), config('campaign.security.hsts') ? 'on' : 'SECURITY_HSTS_ENABLED is off');
        $add('security', 'Database TLS required', in_array(config('database.connections.pgsql.sslmode'), ['require', 'verify-ca', 'verify-full'], true), 'DB_SSLMODE='.config('database.connections.pgsql.sslmode'));
        $add('security', 'Session, cache and queue on Redis', config('queue.default') === 'redis' && config('cache.default') === 'redis' && in_array(config('session.driver'), ['redis', 'database'], true),
            'queue='.config('queue.default').', cache='.config('cache.default').', session='.config('session.driver'));

        // Secrets: presence and strength only
        foreach (['campaign.keys.phone_hash' => 'PHONE_HASH_KEY', 'campaign.keys.field_encryption' => 'FIELD_ENCRYPTION_KEY'] as $key => $env) {
            $value = (string) config($key);
            $add('secrets', $env.' set', $value !== '' && ! str_contains(base64_decode(str_replace('base64:', '', $value), true) ?: '', 'test-only'), $value === '' ? 'missing' : 'set');
        }
        $secret = (string) config('services.africastalking.webhook_secret');
        $add('secrets', 'Webhook secret strong', strlen($secret) >= 32 && ! str_starts_with($secret, 'dev-'), $secret === '' ? 'missing' : 'length '.strlen($secret));

        // Providers
        $add('providers', 'SMS uses a real provider', config('campaign.sms.driver') !== 'outbox', 'SMS_DRIVER='.config('campaign.sms.driver'));
        $add('providers', "Africa's Talking credentials", filled(config('services.africastalking.api_key')) && config('services.africastalking.username') !== 'sandbox', 'username='.(config('services.africastalking.username') === 'sandbox' ? 'sandbox' : 'set'));
        $add('providers', 'Sender ID configured', filled(config('services.africastalking.sender_id')), filled(config('services.africastalking.sender_id')) ? 'set' : 'missing', blocker: false);
        $add('providers', 'WhatsApp driver', config('services.whatsapp.driver') !== 'outbox', 'WHATSAPP_DRIVER='.config('services.whatsapp.driver'), blocker: false);

        // Storage
        $add('storage', 'Evidence on private storage', config('filesystems.disks.'.config('campaign.election.evidence_disk').'.visibility') !== 'public' && config('campaign.election.evidence_disk') !== 'public', 'EVIDENCE_DISK='.config('campaign.election.evidence_disk'));
        $add('storage', 'Exports on private storage', config('campaign.privacy.exports_disk') !== 'public', 'EXPORTS_DISK='.config('campaign.privacy.exports_disk'));

        // Content that must replace placeholders
        $notice = $this->safe(fn () => PrivacyNotice::current());
        $add('content', 'Approved privacy notice published', $notice !== null && ! str_starts_with((string) $notice->version, 'PLACEHOLDER'), $notice ? 'version '.$notice->version : 'none published');
        foreach (['candidate_name', 'office', 'tagline'] as $field) {
            $value = (string) config('campaign.public.'.$field);
            $add('content', 'Public '.str_replace('_', ' ', $field).' supplied', $value !== '' && ! str_contains($value, '[PLACEHOLDER'), str_contains($value, '[PLACEHOLDER') ? 'placeholder' : 'set');
        }
        foreach (['about', 'manifesto'] as $slug) {
            $page = $this->safe(fn () => ContentItem::query()->where('type', ContentType::Page)->where('slug', $slug)->first());
            $add('content', ucfirst($slug).' page published', $page?->isLive() === true && ! str_contains((string) $page->live('body_en'), '[PLACEHOLDER'), $page === null ? 'missing' : ($page->isLive() ? 'live' : 'not published'), blocker: false);
        }

        // Data
        $sample = $this->safe(fn () => $this->sampleRows()) ?? [];
        $add('data', 'No SAMPLE records', $sample === [], $sample === [] ? 'none' : implode(', ', array_map(fn ($t, $n) => "{$t}: {$n}", array_keys($sample), $sample)));
        $units = (int) $this->safe(fn () => PollingUnit::query()->where('is_sample', false)->count());
        $add('data', 'Official polling units imported', $units > 0, number_format($units).' polling units', blocker: false);
        $admins = $this->safe(fn () => User::query()->where('status', 'active')->whereHas('activeAssignments.role', fn ($q) => $q->where('slug', 'super_admin'))->get()) ?? collect();
        $add('data', 'At least two super administrators', $admins->count() >= 2, $admins->count().' active', blocker: false);
        $add('data', 'Super administrators use two-factor', $admins->isNotEmpty() && $admins->every(fn (User $u) => $u->two_factor_confirmed_at !== null), $admins->filter(fn (User $u) => $u->two_factor_confirmed_at === null)->count().' without');

        // Operations
        $audit = $this->safe(fn () => app(AuditChainVerifier::class)->verify());
        $add('operations', 'Audit chain intact', ($audit['valid'] ?? false) === true, $audit ? $audit['checked'].' entries checked' : 'could not verify');
        $health = $this->safe(fn () => app(HealthChecker::class)->run()) ?? [];
        foreach ($health as $result) {
            $add('operations', 'Health: '.$result->name, $result->status->value === 'ok', $result->status->value, blocker: $result->status->value === 'failed');
        }
        $add('operations', 'Scheduler heartbeat', Cache::has(HealthChecker::SCHEDULER_HEARTBEAT), Cache::has(HealthChecker::SCHEDULER_HEARTBEAT) ? 'recent' : 'none', blocker: false);

        return $checks;
    }

    /** @return array<string, int> table => count of SAMPLE rows */
    private function sampleRows(): array
    {
        $counts = [];
        foreach (DB::select("select table_name from information_schema.columns where column_name = 'is_sample' and table_schema = current_schema() order by table_name") as $row) {
            $n = DB::table($row->table_name)->where('is_sample', true)->count();
            if ($n > 0) {
                $counts[$row->table_name] = $n;
            }
        }

        return $counts;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $probe
     * @return T|null
     */
    private function safe(callable $probe): mixed
    {
        try {
            return $probe();
        } catch (Throwable) {
            return null;
        }
    }
}
