<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Settings;

use App\Livewire\Concerns\ChecksRecentConfirmation;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Settings\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Runtime settings declared in config/settings.php. Every change is audited. */
final class SettingsPage extends Component
{
    use ChecksRecentConfirmation;

    /** @var array<string, mixed> setting key (dots replaced by "__") => value */
    public array $values = [];

    public function mount(Settings $settings): void
    {
        $this->authorize('settings.manage');

        foreach (array_keys((array) config('settings')) as $key) {
            $this->values[self::field($key)] = $settings->get($key);
        }
    }

    public function save(string $key, Settings $settings): void
    {
        $this->authorize('settings.manage');
        if (! $this->recentlyConfirmed()) {
            $this->requireConfirmation();

            return;
        }

        $definition = Settings::definition($key);
        $field = 'values.'.self::field($key);

        $rules = match ($definition['type']) {
            'integer' => ['required', 'integer', 'min:'.($definition['min'] ?? PHP_INT_MIN), 'max:'.($definition['max'] ?? PHP_INT_MAX)],
            'boolean' => ['boolean'],
            default => ['required', 'string', 'max:500'],
        };
        $this->validate([$field => $rules], [], [$field => $definition['label']]);

        $user = Auth::user();
        assert($user instanceof User);

        if ($settings->get($key) == data_get($this->values, self::field($key))) {
            return;
        }

        $settings->set($key, data_get($this->values, self::field($key)), $user);
        $this->dispatch('toast', type: 'success', title: __('settings.saved', ['setting' => $definition['label']]));
    }

    public function render(Settings $settings): View
    {
        $definitions = collect((array) config('settings'))->map(fn (array $d, string $key) => $d + ['key' => $key, 'field' => self::field($key), 'current' => $settings->get($key)]);

        return view('livewire.workspace.settings.settings-page', [
            'definitions' => $definitions->groupBy(fn (array $d) => explode('.', $d['key'])[0]),
            'history' => AuditLog::query()->where('action', 'settings.changed')->with('actor:id,name')->latest('id')->limit(15)->get(),
        ])->title(__('settings.title'))->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('settings.title') => null]]);
    }

    /** Livewire property paths cannot contain dots inside a key. */
    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
