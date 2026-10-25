<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\DsrStatus;
use App\Enums\ImportStatus;
use App\Enums\QualityFlag;
use App\Enums\VerificationStatus;
use App\Enums\VolunteerStatus;
use App\Models\ContactAttempt;
use App\Models\DataSubjectRequest;
use App\Models\ImportJob;
use App\Models\MessageCampaign;
use App\Models\Supporter;
use App\Models\Task;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\Messaging\CampaignService;

/**
 * Everything waiting for a decision by this person, limited to their
 * permissions and area. Later phases add message, content and result queues.
 */
final class ApprovalQueues
{
    /**
     * @return list<array{key: string, label: string, icon: string, count: int, url: string, items: list<array{title: string, meta: string, url: string, urgent: bool}>}>
     */
    public function for(User $user, int $items = 5): array
    {
        $tz = (string) config('campaign.display_timezone');
        $queues = [];

        if ($user->can('volunteers.approve')) {
            $query = Volunteer::query()->visibleTo($user, 'volunteers.approve')->where('status', VolunteerStatus::Applied);
            $queues[] = $this->queue('volunteers', __('approvals.volunteers'), 'hand-heart', (clone $query)->count(), route('workspace.volunteers.index'),
                $query->with(['ward:id,name', 'lga:id,name'])->oldest('applied_at')->limit($items)->get()->map(fn (Volunteer $v) => [
                    'title' => $v->full_name,
                    'meta' => collect([$v->ward?->name, $v->lga?->name])->filter()->implode(' · ').' · '.$v->applied_at->timezone($tz)->format('j M'),
                    'url' => route('workspace.volunteers.show', $v),
                    'urgent' => $v->applied_at->lt(now()->subDays(7)),
                ])->all());
        }

        if ($user->can('supporters.verify')) {
            $query = Supporter::query()->visibleTo($user, 'supporters.verify')->whereNull('erased_at')->where('verification_status', VerificationStatus::PhoneVerified);
            $queues[] = $this->queue('supporters', __('approvals.supporters'), 'users', (clone $query)->count(), route('workspace.supporters.index', ['status' => VerificationStatus::PhoneVerified->value]),
                $query->with('lga:id,name')->oldest()->limit($items)->get()->map(fn (Supporter $s) => [
                    'title' => $s->full_name,
                    'meta' => $s->reference.' · '.($s->lga?->name ?? ''),
                    'url' => route('workspace.supporters.show', $s),
                    'urgent' => false,
                ])->all());
        }

        if ($user->can('canvassing.manage')) {
            $query = ContactAttempt::query()->visibleTo($user, 'canvassing.manage')->where('quality_flag', '!=', QualityFlag::Ok)->whereNull('reviewed_at');
            $queues[] = $this->queue('quality', __('approvals.quality'), 'copy', (clone $query)->count(), route('workspace.canvassing.index', ['view' => 'review']),
                $query->with('user:id,name')->latest('occurred_at')->limit($items)->get()->map(fn (ContactAttempt $c) => [
                    'title' => $c->quality_flag->label().' · '.$c->outcome->label(),
                    'meta' => $c->user->name.' · '.$c->occurred_at->timezone($tz)->format('j M, H:i'),
                    'url' => route('workspace.canvassing.index', ['view' => 'review']),
                    'urgent' => false,
                ])->all());
        }

        if ($user->can('tasks.manage')) {
            $query = Task::query()->visibleTo($user, 'tasks.manage')->open()->where('escalation_level', '>', 0);
            $queues[] = $this->queue('tasks', __('approvals.tasks'), 'triangle-alert', (clone $query)->count(), route('workspace.tasks.index', ['view' => 'area', 'overdue' => 1]),
                $query->with(['ward:id,name', 'lga:id,name', 'assignee:id,name'])->orderByDesc('escalation_level')->orderBy('due_at')->limit($items)->get()->map(fn (Task $t) => [
                    'title' => $t->title,
                    'meta' => $t->areaLabel().' · '.($t->assignee?->name ?? __('tasks.unassigned')),
                    'url' => route('workspace.tasks.show', $t),
                    'urgent' => $t->escalation_level > 1,
                ])->all());
        }

        if ($user->can('dsr.manage')) {
            $query = DataSubjectRequest::query()->whereNotIn('status', [DsrStatus::Completed, DsrStatus::Rejected]);
            $queues[] = $this->queue('dsr', __('approvals.dsr'), 'lock-keyhole', (clone $query)->count(), route('workspace.privacy.index'),
                $query->orderBy('due_at')->limit($items)->get()->map(fn (DataSubjectRequest $r) => [
                    'title' => $r->reference.' · '.$r->type->label(),
                    'meta' => __('approvals.due', ['date' => $r->due_at->timezone($tz)->format('j M')]),
                    'url' => route('workspace.privacy.requests.show', $r),
                    'urgent' => $r->due_at->lt(now()->addDays(3)),
                ])->all());
        }

        if ($user->can('messaging.approve')) {
            $campaigns = app(CampaignService::class);
            $pending = MessageCampaign::query()->where('status', 'pending_approval')->with('creator:id,name')->oldest('submitted_at')->get()
                ->filter(fn ($c) => $c->created_by !== $user->id && $campaigns->canApproveScope($c, $user))->values();
            $queues[] = $this->queue('messaging', __('approvals.messaging'), 'send', $pending->count(), route('workspace.messaging.index', ['status' => 'pending_approval']),
                $pending->take($items)->map(fn ($c) => [
                    'title' => $c->name,
                    'meta' => $c->creator?->name.' · '.trans_choice('messaging.recipients_count', $c->estimated_recipients, ['count' => number_format($c->estimated_recipients)]).' · ₦'.number_format((float) $c->estimated_cost, 2),
                    'url' => route('workspace.messaging.show', $c),
                    'urgent' => $c->scheduled_at !== null && $c->scheduled_at->lt(now()->addHours(6)),
                ])->all());
        }

        if ($user->can('geography.import')) {
            $query = ImportJob::query()->where('status', ImportStatus::Validated);
            $queues[] = $this->queue('imports', __('approvals.imports'), 'upload', (clone $query)->count(), route('workspace.geography.imports'),
                $query->latest()->limit($items)->get()->map(fn (ImportJob $j) => [
                    'title' => $j->original_name,
                    'meta' => __('approvals.import_rows', ['valid' => $j->valid_rows, 'invalid' => $j->invalid_rows]),
                    'url' => route('workspace.geography.imports.show', $j),
                    'urgent' => false,
                ])->all());
        }

        return $queues;
    }

    public function total(User $user): int
    {
        return array_sum(array_column($this->for($user, 0), 'count'));
    }

    /**
     * @param  list<array{title: string, meta: string, url: string, urgent: bool}>  $items
     * @return array{key: string, label: string, icon: string, count: int, url: string, items: list<array{title: string, meta: string, url: string, urgent: bool}>}
     */
    private function queue(string $key, string $label, string $icon, int $count, string $url, array $items): array
    {
        return compact('key', 'label', 'icon', 'count', 'url', 'items');
    }
}
