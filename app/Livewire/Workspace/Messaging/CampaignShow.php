<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Messaging;

use App\Enums\SkipReason;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\MessageCampaign;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\MessageRenderer;
use App\Support\LowData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

final class CampaignShow extends Component
{
    use WithWorkspacePagination;

    #[Locked]
    public MessageCampaign $campaign;

    #[Url(except: '')]
    public string $messageStatus = '';

    public string $note = '';

    public function mount(MessageCampaign $campaign, CampaignService $campaigns): void
    {
        abort_unless($campaigns->canView($campaign, $this->actor()), 404);
        $this->campaign = $campaign;
    }

    public function updatedMessageStatus(): void
    {
        $this->resetPage();
    }

    public function approve(CampaignService $campaigns): void
    {
        $this->act(fn () => $campaigns->approve($this->campaign, $this->actor()), __('messaging.approved_ok'));
    }

    public function reject(CampaignService $campaigns): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:500']], [], ['note' => __('volunteers.reason')]);
        $this->act(fn () => $campaigns->reject($this->campaign, $this->actor(), $this->note), __('messaging.rejected_ok'));
        $this->dispatch('close-modal', name: 'reject-campaign');
    }

    public function submit(CampaignService $campaigns): void
    {
        $this->act(fn () => $campaigns->submit($this->campaign, $this->actor()), __('messaging.submitted'));
    }

    public function pause(CampaignService $campaigns): void
    {
        $this->act(fn () => $campaigns->pause($this->campaign, $this->actor(), __('messaging.paused_by', ['name' => $this->actor()->name])), __('messaging.paused_ok'));
    }

    public function resume(CampaignService $campaigns): void
    {
        $this->act(fn () => $campaigns->resume($this->campaign, $this->actor()), __('messaging.resumed_ok'));
    }

    public function cancel(CampaignService $campaigns): void
    {
        $this->act(fn () => $campaigns->cancel($this->campaign, $this->actor()), __('messaging.cancelled_ok'));
    }

    private function act(callable $action, string $success): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', title: collect($e->errors())->flatten()->first());

            return;
        }

        $this->campaign->refresh();
        $this->dispatch('toast', type: 'success', title: $success);
    }

    public function render(CampaignService $campaigns, MessageRenderer $renderer): View
    {
        $actor = $this->actor();
        $campaign = $this->campaign->load(['template', 'creator:id,name', 'approver:id,name']);

        $counts = $campaign->messages()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $skips = $campaign->messages()->toBase()->where('status', 'skipped')->selectRaw('skip_reason, count(*) as n')->groupBy('skip_reason')->pluck('n', 'skip_reason');

        return view('livewire.workspace.messaging.campaign-show', [
            'counts' => $counts,
            'total' => $counts->sum(),
            'skips' => $skips->mapWithKeys(fn ($n, $reason) => [SkipReason::tryFrom((string) $reason)?->label() ?? (string) $reason => (int) $n]),
            'messages' => $campaign->messages()->with('lga:id,name')->when($this->messageStatus !== '', fn ($q) => $q->where('status', $this->messageStatus))->latest('id')->paginate(20),
            'history' => WorkflowTransition::query()->where('subject_type', $campaign->getMorphClass())->where('subject_id', $campaign->id)->with('user:id,name')->latest('id')->get(),
            'canManage' => $campaigns->canManage($campaign, $actor),
            'canApprove' => $campaigns->canApproveScope($campaign, $actor) && $campaign->created_by !== $actor->id,
            'needsSecond' => $campaigns->requiresSecondApprover($campaign),
            'preview' => $renderer->render($campaign->template->body_en, ['first_name' => 'Ada', 'lga' => 'Udu', 'ward' => 'Sample Ward', 'reference' => 'SUP-SAMPLE01']),
            'poll' => $campaign->status->value === 'sending' && ! LowData::enabled(),
        ])->title($campaign->name)->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('messaging.title') => route('workspace.messaging.index'), $campaign->name => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
