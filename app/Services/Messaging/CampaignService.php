<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\CampaignStatus;
use App\Enums\MessageStatus;
use App\Enums\ScopeType;
use App\Jobs\BuildCampaignRecipients;
use App\Models\MessageCampaign;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Notifications\CampaignAwaitingApproval;
use App\Notifications\CampaignDecided;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\GeoScope;
use App\Services\Settings\Settings;
use App\Services\Supporters\StaleRecord;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Message campaigns: draft → submit → independent approval (for large sends)
 * → scheduled/sending → completed; pause, resume and cancel at any time.
 * Who created and who approved each campaign is recorded and audited.
 */
final class CampaignService
{
    public function __construct(
        private readonly AudienceBuilder $audience,
        private readonly MessageRenderer $renderer,
        private readonly MessagingBudget $budget,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, template_id: int, lga_ids?: list<int>, ward_ids?: list<int>, verification?: list<string>, volunteers_only?: bool, language?: string, scheduled_at?: \DateTimeInterface|null}  $data
     */
    public function saveDraft(User $by, array $data, ?MessageCampaign $campaign = null, ?int $expectedVersion = null): MessageCampaign
    {
        if (! $by->hasPermission('messaging.create')) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        if ($campaign !== null && (! $campaign->status->isEditable() || ! $this->canManage($campaign, $by))) {
            throw new AuthorizationDenied(__('messaging.not_editable'));
        }
        if ($campaign !== null && $expectedVersion !== null && $campaign->version !== $expectedVersion) {
            throw new StaleRecord;
        }

        $template = MessageTemplate::query()->where('status', 'active')->findOrFail($data['template_id']);
        $scope = $by->geoScope('messaging.create');

        foreach ($data['lga_ids'] ?? [] as $id) {
            if (! $scope->covers(ScopeType::Lga, (int) $id)) {
                throw ValidationException::withMessages(['lga_ids' => __('messaging.outside_area')]);
            }
        }
        foreach ($data['ward_ids'] ?? [] as $id) {
            if (! $scope->covers(ScopeType::Ward, (int) $id)) {
                throw ValidationException::withMessages(['ward_ids' => __('messaging.outside_area')]);
            }
        }

        $audience = [
            'scope' => $scope->toArray(),
            'lga_ids' => array_values(array_map('intval', $data['lga_ids'] ?? [])),
            'ward_ids' => array_values(array_map('intval', $data['ward_ids'] ?? [])),
            'verification' => array_values($data['verification'] ?? []),
            'volunteers_only' => (bool) ($data['volunteers_only'] ?? false),
            'language' => (string) ($data['language'] ?? ''),
        ];

        $campaign ??= new MessageCampaign(['created_by' => $by->id]);
        $campaign->fill([
            'name' => mb_substr($data['name'], 0, 150),
            'message_template_id' => $template->id,
            'channel' => $template->channel,
            'purpose' => $template->purpose,
            'audience' => $audience,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => CampaignStatus::Draft,
        ]);
        $campaign->setRelation('template', $template);
        $this->estimate($campaign);
        $campaign->version = $campaign->exists ? $campaign->version + 1 : 1;
        $campaign->save();

        $this->audit->record($campaign->wasRecentlyCreated ? 'messaging.campaign_created' : 'messaging.campaign_updated', $campaign, null, ['recipients' => $campaign->estimated_recipients], $by);

        return $campaign;
    }

    /** Fills estimated recipients, segments and cost. */
    public function estimate(MessageCampaign $campaign): void
    {
        $template = $campaign->template;
        $counts = $this->audience->counts($campaign->audience, $campaign->channel, $campaign->purpose);
        $segments = max($this->renderer->segments($this->renderer->worstCase($template->body_en)), filled($template->body_pcm) ? $this->renderer->segments($this->renderer->worstCase((string) $template->body_pcm)) : 1);

        $campaign->estimated_recipients = $counts['eligible'];
        $campaign->segments = $campaign->channel->value === 'sms' ? $segments : 1;
        $campaign->estimated_cost = round($counts['eligible'] * $this->budget->unitCost($campaign->channel, $segments), 2);
    }

    public function requiresSecondApprover(MessageCampaign $campaign): bool
    {
        return $campaign->estimated_recipients >= (int) $this->settings->get('messaging.approval_threshold');
    }

    public function submit(MessageCampaign $campaign, User $by): void
    {
        if (! $campaign->status->isEditable() || ! $this->canManage($campaign, $by)) {
            throw new AuthorizationDenied(__('messaging.not_editable'));
        }

        $campaign->loadMissing('template');
        $this->estimate($campaign);

        if ($campaign->estimated_recipients === 0) {
            throw ValidationException::withMessages(['audience' => __('messaging.no_recipients')]);
        }
        if (! $this->budget->allows((float) $campaign->estimated_cost)) {
            throw ValidationException::withMessages(['audience' => __('messaging.over_budget', ['remaining' => number_format($this->budget->remaining(), 2)])]);
        }

        DB::transaction(function () use ($campaign, $by): void {
            $campaign->forceFill(['status' => CampaignStatus::PendingApproval, 'submitted_at' => now(), 'decision_note' => null, 'version' => $campaign->version + 1])->save();
            WorkflowTransition::record($campaign, CampaignStatus::Draft->value, CampaignStatus::PendingApproval->value, $by);
            $this->audit->record('messaging.campaign_submitted', $campaign, null, ['recipients' => $campaign->estimated_recipients, 'cost' => (string) $campaign->estimated_cost], $by);
        });

        // Small sends by someone who may approve go straight through; large sends need a different person.
        if (! $this->requiresSecondApprover($campaign) && $this->canApproveScope($campaign, $by)) {
            $this->approve($campaign, $by, self: true);

            return;
        }

        foreach ($this->approversFor($campaign)->reject(fn (User $u) => $u->id === $by->id) as $approver) {
            $approver->notify(new CampaignAwaitingApproval($campaign));
        }
    }

    public function approve(MessageCampaign $campaign, User $by, bool $self = false): void
    {
        if ($campaign->status !== CampaignStatus::PendingApproval) {
            throw ValidationException::withMessages(['status' => __('messaging.not_pending')]);
        }
        if (! $this->canApproveScope($campaign, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
        if (! $self && $campaign->created_by === $by->id) {
            throw new AuthorizationDenied(__('messaging.not_own'));
        }
        if ($self && $this->requiresSecondApprover($campaign)) {
            throw new AuthorizationDenied(__('messaging.needs_second'));
        }
        if (! $this->budget->allows((float) $campaign->estimated_cost)) {
            throw ValidationException::withMessages(['budget' => __('messaging.over_budget', ['remaining' => number_format($this->budget->remaining(), 2)])]);
        }

        $scheduled = $campaign->scheduled_at !== null && $campaign->scheduled_at->isFuture();

        DB::transaction(function () use ($campaign, $by, $scheduled, $self): void {
            $campaign->forceFill(['status' => CampaignStatus::Scheduled, 'approved_by' => $by->id, 'approved_at' => now(), 'version' => $campaign->version + 1])->save();
            WorkflowTransition::record($campaign, CampaignStatus::PendingApproval->value, CampaignStatus::Scheduled->value, $by, $self ? 'Below the approval threshold' : null);
            $this->audit->record('messaging.campaign_approved', $campaign, null, ['self' => $self, 'scheduled' => $scheduled], $by);
        });

        if (! $self) {
            $campaign->loadMissing('creator')->creator?->notify(new CampaignDecided($campaign, true));
        }
        if (! $scheduled) {
            $this->start($campaign);
        }
    }

    public function reject(MessageCampaign $campaign, User $by, string $note): void
    {
        if ($campaign->status !== CampaignStatus::PendingApproval) {
            throw ValidationException::withMessages(['status' => __('messaging.not_pending')]);
        }
        if (! $this->canApproveScope($campaign, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $campaign->forceFill(['status' => CampaignStatus::Rejected, 'approved_by' => $by->id, 'approved_at' => now(), 'decision_note' => mb_substr($note, 0, 500), 'version' => $campaign->version + 1])->save();
        WorkflowTransition::record($campaign, CampaignStatus::PendingApproval->value, CampaignStatus::Rejected->value, $by, $note);
        $this->audit->record('messaging.campaign_rejected', $campaign, null, ['note' => $note], $by);
        $campaign->loadMissing('creator')->creator?->notify(new CampaignDecided($campaign, false));
    }

    /** Begins sending: recipients are built in the background, then each message is checked and sent. */
    public function start(MessageCampaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Scheduled) {
            return;
        }

        $campaign->forceFill(['status' => CampaignStatus::Sending, 'started_at' => now()])->save();
        WorkflowTransition::record($campaign, CampaignStatus::Scheduled->value, CampaignStatus::Sending->value, null);
        BuildCampaignRecipients::dispatch($campaign->id);
    }

    public function pause(MessageCampaign $campaign, ?User $by, string $reason): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Sending, CampaignStatus::Scheduled], true)) {
            return;
        }
        if ($by !== null) {
            $this->assertCanControl($campaign, $by);
        }

        $from = $campaign->status;
        $campaign->forceFill(['status' => CampaignStatus::Paused, 'pause_reason' => mb_substr($reason, 0, 200)])->save();
        WorkflowTransition::record($campaign, $from->value, CampaignStatus::Paused->value, $by, $reason);
        $this->audit->as($by ?? 'messaging', fn () => $this->audit->record('messaging.campaign_paused', $campaign, null, ['reason' => $reason]));
    }

    public function resume(MessageCampaign $campaign, User $by): void
    {
        $this->assertCanControl($campaign, $by);

        if ($campaign->status !== CampaignStatus::Paused) {
            return;
        }

        $to = $campaign->started_at === null ? CampaignStatus::Scheduled : CampaignStatus::Sending;
        $campaign->forceFill(['status' => $to, 'pause_reason' => null])->save();
        WorkflowTransition::record($campaign, CampaignStatus::Paused->value, $to->value, $by);
        $this->audit->record('messaging.campaign_resumed', $campaign, null, [], $by);

        if ($to === CampaignStatus::Scheduled && ($campaign->scheduled_at === null || $campaign->scheduled_at->isPast())) {
            $this->start($campaign);
        }
    }

    public function cancel(MessageCampaign $campaign, User $by): void
    {
        if ($campaign->status->isFinished()) {
            return;
        }
        if ($campaign->status->isEditable() || $campaign->status === CampaignStatus::PendingApproval) {
            if (! $this->canManage($campaign, $by) && ! $this->canApproveScope($campaign, $by)) {
                throw new AuthorizationDenied(__('users.not_allowed'));
            }
        } else {
            $this->assertCanControl($campaign, $by);
        }

        DB::transaction(function () use ($campaign, $by): void {
            $from = $campaign->status;
            $campaign->forceFill(['status' => CampaignStatus::Cancelled, 'completed_at' => now()])->save();
            $campaign->messages()->where('status', MessageStatus::Queued)->update(['status' => MessageStatus::Cancelled, 'updated_at' => now()]);
            WorkflowTransition::record($campaign, $from->value, CampaignStatus::Cancelled->value, $by);
            $this->audit->record('messaging.campaign_cancelled', $campaign, null, [], $by);
        });
    }

    /** Marks a sending campaign completed when nothing is left to send. */
    public function completeIfDone(MessageCampaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Sending || $campaign->messages()->where('status', MessageStatus::Queued)->exists()) {
            return;
        }
        if ($campaign->messages()->doesntExist() && $campaign->started_at?->gt(now()->subMinutes(10))) {
            return; // Recipients may still be building.
        }

        $campaign->forceFill(['status' => CampaignStatus::Completed, 'completed_at' => now()])->save();
        WorkflowTransition::record($campaign, CampaignStatus::Sending->value, CampaignStatus::Completed->value, null);
    }

    public function canManage(MessageCampaign $campaign, User $user): bool
    {
        return $user->hasPermission('messaging.create') && ($campaign->created_by === null || $campaign->created_by === $user->id || $this->coversScope($user->geoScope('messaging.create'), $this->scopeOf($campaign)));
    }

    public function canApproveScope(MessageCampaign $campaign, User $user): bool
    {
        return $user->hasPermission('messaging.approve') && $this->coversScope($user->geoScope('messaging.approve'), $this->scopeOf($campaign));
    }

    public function canView(MessageCampaign $campaign, User $user): bool
    {
        return $campaign->created_by === $user->id
            || ($user->hasPermission('messaging.view') && $this->coversScope($user->geoScope('messaging.view'), $this->scopeOf($campaign)))
            || $this->canApproveScope($campaign, $user);
    }

    /** @return Collection<int, User> */
    public function approversFor(MessageCampaign $campaign): Collection
    {
        return User::query()->where('status', 'active')
            ->whereHas('activeAssignments.role.permissions', fn ($q) => $q->where('slug', 'messaging.approve'))
            ->get()->filter(fn (User $u) => $this->canApproveScope($campaign, $u))->values();
    }

    public function scopeOf(MessageCampaign $campaign): GeoScope
    {
        return GeoScope::fromArray((array) ($campaign->audience['scope'] ?? []));
    }

    /** Whether everything in $target lies inside $scope. */
    public function coversScope(GeoScope $scope, GeoScope $target): bool
    {
        if ($scope->statewide) {
            return true;
        }
        if ($target->statewide || $target->isEmpty()) {
            return false;
        }

        foreach ([[ScopeType::District, $target->districtIds], [ScopeType::Lga, $target->lgaIds], [ScopeType::Ward, $target->wardIds], [ScopeType::PollingUnit, $target->pollingUnitIds]] as [$type, $ids]) {
            foreach ($ids as $id) {
                if (! $scope->covers($type, (int) $id)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function assertCanControl(MessageCampaign $campaign, User $by): void
    {
        if (! $this->canManage($campaign, $by) && ! $this->canApproveScope($campaign, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
