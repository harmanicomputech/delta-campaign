<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Logging\RedactSensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes append-only, hash-chained audit entries.
 *
 * Entries are serialised with a transaction-scoped advisory lock so concurrent
 * writers cannot fork the chain. If the surrounding transaction rolls back, the
 * entry rolls back with it: the log records what actually happened.
 */
final class AuditLogger
{
    private const int LOCK_KEY = 727_202_701;

    /** Attributes never written to the audit log, even redacted. */
    private const array EXCLUDED = [
        'phone_encrypted', 'phone_hash', 'email_encrypted', 'two_factor_secret', 'two_factor_recovery_codes',
        'pin_hash', 'remember_token', 'code_hash', 'fingerprint_hash', 'payload', 'qr_token', 'qr_token_hash',
    ];

    private ?User $actorOverride = null;

    private ?string $actorLabel = null;

    /**
     * @param  array<string, mixed>|null  $changes  ['field' => ['from' => …, 'to' => …]] or a flat map
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, Model|string|null $subject = null, ?array $changes = null, array $context = [], ?User $actor = null): AuditLog
    {
        $actor ??= $this->actorOverride ?? $this->currentUser();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        [$subjectType, $subjectId] = match (true) {
            $subject instanceof Model => [$subject->getMorphClass(), (string) $subject->getKey()],
            is_string($subject) => [$subject, null],
            default => [null, null],
        };

        $entry = [
            'uuid' => (string) Str::uuid(),
            'occurred_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s.uP'),
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor === null ? ($this->actorLabel ?? (app()->runningInConsole() ? 'system' : 'anonymous')) : null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'request_id' => Context::get('request_id'),
            'changes' => $changes === null ? null : $this->sanitise($changes),
            'context' => $context === [] ? null : $this->sanitise($context),
        ];

        return DB::transaction(function () use ($entry): AuditLog {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

            $previous = DB::table('audit_logs')->orderByDesc('id')->value('hash');
            $entry['previous_hash'] = $previous;
            $entry['hash'] = self::hashEntry($entry, $previous);

            $row = $entry;
            $row['changes'] = $entry['changes'] === null ? null : json_encode($entry['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $row['context'] = $entry['context'] === null ? null : json_encode($entry['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $id = DB::table('audit_logs')->insertGetId($row);

            return AuditLog::query()->findOrFail($id);
        });
    }

    /**
     * Records a model's dirty attributes as from/to pairs (sensitive fields excluded).
     *
     * @param  array<string, mixed>  $context
     */
    public function recordChanges(string $action, Model $model, array $context = []): ?AuditLog
    {
        $changes = [];

        foreach ($model->getChanges() as $key => $to) {
            if (in_array($key, ['updated_at', 'created_at', 'version'], true) || in_array($key, self::EXCLUDED, true)) {
                continue;
            }
            $changes[$key] = ['from' => self::scalar($model->getOriginal($key)), 'to' => self::scalar($to)];
        }

        return $changes === [] && $context === [] ? null : $this->record($action, $model, $changes ?: null, $context);
    }

    /** Runs $callback with audit entries attributed to $label (e.g. "ussd", "scheduler") or a user. */
    public function as(User|string|null $actor, callable $callback): mixed
    {
        [$previousUser, $previousLabel] = [$this->actorOverride, $this->actorLabel];
        $this->actorOverride = $actor instanceof User ? $actor : null;
        $this->actorLabel = is_string($actor) ? $actor : null;

        try {
            return $callback();
        } finally {
            [$this->actorOverride, $this->actorLabel] = [$previousUser, $previousLabel];
        }
    }

    /**
     * The chain hash for an entry. Shared with the verifier so both compute identically.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function hashEntry(array $entry, ?string $previousHash): string
    {
        $fields = [];
        foreach (['uuid', 'occurred_at', 'actor_id', 'actor_label', 'action', 'subject_type', 'subject_id', 'ip', 'user_agent', 'request_id', 'changes', 'context'] as $key) {
            $fields[$key] = $entry[$key] ?? null;
        }

        return hash('sha256', ($previousHash ?? 'GENESIS').'|'.self::canonicalJson($fields));
    }

    public static function canonicalJson(mixed $value): string
    {
        return json_encode(self::sortKeys($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sortKeys(...), $value);
    }

    /**
     * Drops excluded keys, masks phone numbers and stringifies floats so values
     * round-trip through PostgreSQL jsonb unchanged (keeping hashes stable).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitise(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::EXCLUDED, true)) {
                continue;
            }

            $clean[$key] = match (true) {
                is_array($value) => $this->sanitise($value),
                is_string($value) => RedactSensitiveData::maskPhoneNumbers($value),
                is_float($value) => (string) $value,
                is_bool($value), is_int($value), $value === null => $value,
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => Carbon::instance($value)->utc()->toIso8601String(),
                default => (string) json_encode($value),
            };
        }

        return $clean;
    }

    private static function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => Carbon::instance($value)->utc()->toIso8601String(),
            is_float($value) => (string) $value,
            is_array($value) => $value,
            is_object($value) => (string) json_encode($value),
            default => $value,
        };
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
