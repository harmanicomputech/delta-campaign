<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Audit;

use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\AuditLog;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

final class AuditLogIndex extends Component
{
    use WithWorkspacePagination;

    #[Url(as: 'action', except: '')]
    public string $actionFilter = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public ?int $inspecting = null;

    /** @var array<string, mixed>|null */
    public ?array $verification = null;

    public function mount(AuditLogger $audit): void
    {
        $this->authorize('audit.view');
        $audit->record('audit.viewed');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['actionFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function inspect(int $id): void
    {
        $this->inspecting = $id;
        $this->dispatch('open-modal', name: 'audit-entry');
    }

    public function verifyChain(AuditChainVerifier $verifier, AuditLogger $audit): void
    {
        $this->authorize('audit.verify');
        $result = $verifier->verify();
        $audit->record('audit.verified', context: ['valid' => $result['valid'], 'checked' => $result['checked'], 'broken_id' => $result['broken_id']]);
        $this->verification = $result + ['at' => now()->toIso8601String()];
    }

    public function render(): View
    {
        $query = AuditLog::query()->with('actor')
            ->when($this->actionFilter !== '', fn (Builder $q) => $q->where('action', 'like', addcslashes($this->actionFilter, '%_\\').'%'))
            ->when($this->validDate($this->from), fn (Builder $q) => $q->where('occurred_at', '>=', Carbon::parse($this->from, config('campaign.display_timezone'))->startOfDay()))
            ->when($this->validDate($this->to), fn (Builder $q) => $q->where('occurred_at', '<=', Carbon::parse($this->to, config('campaign.display_timezone'))->endOfDay()))
            ->orderByDesc('id');

        return view('livewire.workspace.audit.audit-log-index', [
            'entries' => $query->paginate(30),
            'entry' => $this->inspecting ? AuditLog::query()->with('actor')->find($this->inspecting) : null,
            'groups' => AuditLog::query()->selectRaw("distinct split_part(action, '.', 1) as grp")->orderBy('grp')->pluck('grp'),
        ])->title(__('audit.title'))->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('audit.title') => null]]);
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
