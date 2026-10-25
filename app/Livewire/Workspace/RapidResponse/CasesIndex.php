<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\RapidResponse;

use App\Enums\CaseStatus;
use App\Enums\TaskPriority;
use App\Livewire\Concerns\WithTableState;
use App\Models\RapidResponseCase;
use App\Models\User;
use App\Services\RapidResponse\RapidResponseService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

final class CasesIndex extends Component
{
    use WithTableState;

    #[Url(except: 'open')]
    public string $status = 'open';

    public string $title = '';

    public string $claim = '';

    public string $sources = '';

    public string $priority = 'normal';

    public string $deadline = '';

    public bool $requiresLegal = false;

    protected function sortableColumns(): array
    {
        return ['created_at', 'deadline_at'];
    }

    public function open(RapidResponseService $cases): void
    {
        $this->authorize('rapid-response.manage');
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'claim' => ['required', 'string', 'min:5', 'max:5000'],
            'sources' => ['nullable', 'string', 'max:3000'],
            'priority' => [Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date', 'after:now'],
        ], [], ['title' => __('rapid.case_title'), 'claim' => __('rapid.claim')]);

        $user = Auth::user();
        assert($user instanceof User);

        $case = $cases->open($user, [
            'title' => $this->title, 'claim' => $this->claim, 'source_urls' => preg_split('/\s+/', $this->sources, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            'priority' => $this->priority, 'deadline_at' => $this->deadline ? CarbonImmutable::parse($this->deadline, (string) config('campaign.display_timezone'))->utc() : null,
            'requires_legal' => $this->requiresLegal,
        ]);

        $this->redirectRoute('workspace.rapid-response.show', $case);
    }

    public function render(): View
    {
        $closed = [CaseStatus::Closed, CaseStatus::Rejected, CaseStatus::Approved];
        $query = RapidResponseCase::query()->with('assignee:id,name')
            ->when($this->status === 'open', fn ($q) => $q->whereNotIn('status', $closed))
            ->when(CaseStatus::tryFrom($this->status), fn ($q, CaseStatus $s) => $q->where('status', $s))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'ilike', $this->likeTerm())->orWhere('reference', 'ilike', $this->likeTerm())));

        return view('livewire.workspace.rapid-response.cases-index', [
            'cases' => $this->applySort($query, 'created_at', 'desc')->paginate($this->perPage),
            'statuses' => CaseStatus::options(),
        ])->title(__('rapid.title'))->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('rapid.title') => null]]);
    }
}
