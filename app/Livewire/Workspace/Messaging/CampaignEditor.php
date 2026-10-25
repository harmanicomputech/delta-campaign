<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Messaging;

use App\Enums\VerificationStatus;
use App\Models\Lga;
use App\Models\MessageCampaign;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Ward;
use App\Services\Messaging\AudienceBuilder;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\MessageRenderer;
use App\Services\Messaging\MessagingBudget;
use App\Services\Settings\Settings;
use App\Services\Supporters\StaleRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Create or edit a draft campaign, with a live audience, cost and approval estimate. */
final class CampaignEditor extends Component
{
    #[Locked]
    public ?int $campaignId = null;

    #[Locked]
    public int $loadedVersion = 1;

    public string $name = '';

    public ?int $templateId = null;

    /** @var list<int|string> */
    public array $lgaIds = [];

    /** @var list<int|string> */
    public array $wardIds = [];

    /** @var list<string> */
    public array $verification = [];

    public bool $volunteersOnly = false;

    public string $language = '';

    public string $scheduledAt = '';

    public string $previewLanguage = 'en';

    public function mount(?MessageCampaign $campaign = null): void
    {
        $this->authorize('messaging.create');

        if ($campaign?->exists) {
            abort_unless(app(CampaignService::class)->canManage($campaign, $this->actor()) && $campaign->status->isEditable(), 404);
            $this->campaignId = $campaign->id;
            $this->loadedVersion = $campaign->version;
            $this->name = $campaign->name;
            $this->templateId = $campaign->message_template_id;
            $this->lgaIds = $campaign->audience['lga_ids'] ?? [];
            $this->wardIds = $campaign->audience['ward_ids'] ?? [];
            $this->verification = $campaign->audience['verification'] ?? [];
            $this->volunteersOnly = (bool) ($campaign->audience['volunteers_only'] ?? false);
            $this->language = (string) ($campaign->audience['language'] ?? '');
            $this->scheduledAt = $campaign->scheduled_at?->timezone(config('campaign.display_timezone'))->format('Y-m-d\TH:i') ?? '';
        }
    }

    public function updatedLgaIds(): void
    {
        $this->wardIds = [];
    }

    public function save(CampaignService $campaigns, bool $submit = false): void
    {
        $this->authorize('messaging.create');
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'templateId' => ['required', 'integer'],
            'lgaIds.*' => ['integer'],
            'wardIds.*' => ['integer'],
            'verification.*' => [Rule::enum(VerificationStatus::class)],
            'language' => [Rule::in(['', 'en', 'pcm'])],
            'scheduledAt' => ['nullable', 'date', 'after:now'],
        ], [], ['templateId' => __('messaging.template'), 'scheduledAt' => __('messaging.schedule')]);

        $existing = $this->campaignId ? MessageCampaign::query()->findOrFail($this->campaignId) : null;

        try {
            $campaign = $campaigns->saveDraft($this->actor(), [
                'name' => $this->name,
                'template_id' => (int) $this->templateId,
                'lga_ids' => array_map('intval', $this->lgaIds),
                'ward_ids' => array_map('intval', $this->wardIds),
                'verification' => $this->verification,
                'volunteers_only' => $this->volunteersOnly,
                'language' => $this->language,
                'scheduled_at' => $this->scheduledAt !== '' ? Carbon::parse($this->scheduledAt, config('campaign.display_timezone'))->utc() : null,
            ], $existing, $existing ? $this->loadedVersion : null);

            if ($submit) {
                $campaigns->submit($campaign, $this->actor());
            }
        } catch (StaleRecord) {
            $this->addError('name', __('messaging.stale'));

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(['lga_ids' => 'lgaIds', 'ward_ids' => 'wardIds'][$field] ?? 'audience', $messages[0]);
            }

            return;
        }

        session()->flash('success', $submit ? __('messaging.submitted') : __('messaging.draft_saved'));
        $this->redirectRoute('workspace.messaging.show', $campaign->refresh());
    }

    public function submit(CampaignService $campaigns): void
    {
        $this->save($campaigns, submit: true);
    }

    public function render(AudienceBuilder $audience, MessageRenderer $renderer, MessagingBudget $budget, Settings $settings): View
    {
        $actor = $this->actor();
        $scope = $actor->geoScope('messaging.create');
        $template = $this->templateId ? MessageTemplate::query()->where('status', 'active')->find($this->templateId) : null;

        $estimate = null;
        if ($template !== null) {
            $filters = ['scope' => $scope->toArray(), 'lga_ids' => array_map('intval', $this->lgaIds), 'ward_ids' => array_map('intval', $this->wardIds),
                'verification' => $this->verification, 'volunteers_only' => $this->volunteersOnly, 'language' => $this->language];
            $counts = $audience->counts($filters, $template->channel, $template->purpose);
            $segments = $template->channel->value === 'sms' ? max($renderer->segments($renderer->worstCase($template->body_en)), $template->body_pcm ? $renderer->segments($renderer->worstCase($template->body_pcm)) : 1) : 1;
            $cost = round($counts['eligible'] * $budget->unitCost($template->channel, $segments), 2);
            $estimate = $counts + [
                'segments' => $segments,
                'cost' => $cost,
                'remaining' => $budget->remaining(),
                'second' => $counts['eligible'] >= (int) $settings->get('messaging.approval_threshold'),
                'preview' => $renderer->render($template->body($this->previewLanguage), ['first_name' => 'Ada', 'lga' => 'Udu', 'ward' => 'Sample Ward', 'reference' => 'SUP-SAMPLE01']),
            ];
        }

        $lgaIds = array_map('intval', $this->lgaIds);

        return view('livewire.workspace.messaging.campaign-editor', [
            'templates' => MessageTemplate::query()->where('status', 'active')->orderBy('name')->get(),
            'template' => $template,
            'estimate' => $estimate,
            'lgas' => Lga::query()->visibleTo($scope)->orderBy('name')->pluck('name', 'id'),
            'wards' => count($lgaIds) === 1 ? Ward::query()->visibleTo($scope)->where('lga_id', $lgaIds[0])->orderBy('name')->pluck('name', 'id')
                : (! $scope->statewide && $scope->lgaIds === [] && $scope->districtIds === [] ? Ward::query()->visibleTo($scope)->orderBy('name')->pluck('name', 'id') : collect()),
            'statewide' => $scope->statewide,
            'statuses' => VerificationStatus::options(),
            'threshold' => (int) $settings->get('messaging.approval_threshold'),
        ])->title($this->campaignId ? __('messaging.edit_campaign') : __('messaging.new_campaign'))
            ->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('messaging.title') => route('workspace.messaging.index'), ($this->campaignId ? __('messaging.edit_campaign') : __('messaging.new_campaign')) => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
