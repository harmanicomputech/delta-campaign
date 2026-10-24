<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime-editable settings with configuration defaults. Every change is audited.
 * Keys are declared in config/settings.php.
 */
final class Settings
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key): mixed
    {
        $definition = self::definition($key);

        $stored = Cache::remember("setting:{$key}", 300, fn () => SystemSetting::query()->find($key)?->value);

        return $stored ?? $definition['default'];
    }

    public function set(string $key, mixed $value, User $by): void
    {
        $definition = self::definition($key);
        $old = $this->get($key);

        $value = match ($definition['type']) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'decimal' => (string) round((float) $value, 4),
            default => (string) $value,
        };

        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by->id]);
        Cache::forget("setting:{$key}");
        $this->audit->record('settings.changed', 'setting:'.$key, ['from' => $old, 'to' => $value], [], $by);
    }

    /**
     * Keys contain dots ("privacy.retention_months"), so they are looked up directly, not as config paths.
     *
     * @return array{type: string, default: mixed, label: string, min?: int, max?: int}
     */
    public static function definition(string $key): array
    {
        return ((array) config('settings'))[$key] ?? throw new \InvalidArgumentException("Unknown setting [{$key}].");
    }
}
