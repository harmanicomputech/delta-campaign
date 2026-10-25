<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Messaging;

use App\Enums\CampaignStatus;
use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\MessageCampaign;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\MessageRenderer;
use App\Services\Messaging\MessagingBudget;
use App\Services\Messaging\ProviderHealth;
use App\Services\Messaging\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

final class MessagingIndex extends Component
{
    use WithWorkspacePagination;

    /** campaigns | templates | health */
    #[Url(except: 'campaigns')]
    public string $tab = 'campaigns';

    #[Url(except: '')]
    public string $status = '';

    // Template form
    public ?int $editingId = null;

    public string $tName = '';

    public string $tChannel = 'sms';

    public string $tPurpose = 'campaign_updates';

    public string $tBodyEn = '';

    public string $tBodyPcm = '';

    public string $tProviderTemplate = '';

    public function mount(): void
    {
        $this->authorize('messaging.view');
        if (! in_array($this->tab, ['campaigns', 'templates', 'health'], true)) {
            $this->tab = 'campaigns';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function editTemplate(?int $id = null): void
    {
        $this->authorize('messaging.templates');
        $this->resetErrorBag();
        $template = $id ? MessageTemplate::query()->findOrFail($id) : null;
        $this->editingId = $template?->id;
        $this->tName = $template->name ?? '';
        $this->tChannel = $template?->channel->value ?? 'sms';
        $this->tPurpose = $template?->purpose->value ?? 'campaign_updates';
        $this->tBodyEn = $template->body_en ?? '';
        $this->tBodyPcm = $template->body_pcm ?? '';
        $this->tProviderTemplate = $template->provider_template ?? '';
        $this->dispatch('open-modal', name: 'template-form');
    }

    public function saveTemplate(TemplateService $templates): void
    {
        $this->authorize('messaging.templates');
        $this->validate([
            'tName' => ['required', 'string', 'min:3', 'max:120'],
            'tBodyEn' => ['required', 'string', 'max:1000'],
            'tBodyPcm' => ['nullable', 'string', 'max:1000'],
            'tProviderTemplate' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]*$/'],
        ], [], ['tName' => __('messaging.template_name'), 'tBodyEn' => __('messaging.body_en'), 'tProviderTemplate' => __('messaging.provider_template')]);

        try {
            $templates->save($this->actor(), [
                'name' => $this->tName, 'channel' => $this->tChannel, 'purpose' => $this->tPurpose,
                'body_en' => $this->tBodyEn, 'body_pcm' => $this->tBodyPcm, 'provider_template' => $this->tProviderTemplate,
            ], $this->editingId ? MessageTemplate::query()->findOrFail($this->editingId) : null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(['channel' => 'tChannel', 'purpose' => 'tPurpose', 'body_en' => 'tBodyEn', 'body_pcm' => 'tBodyPcm', 'provider_template' => 'tProviderTemplate'][$field] ?? $field, $messages[0]);
            }

            return;
        }

        $this->dispatch('close-modal', name: 'template-form');
        $this->dispatch('toast', type: 'success', title: __('messaging.template_saved'));
    }

    public function archiveTemplate(int $id, TemplateService $templates): void
    {
        $templates->archive($this->actor(), MessageTemplate::query()->findOrFail($id));
        $this->dispatch('toast', type: 'success', title: __('messaging.template_archived'));
    }

    public function render(CampaignService $campaigns, MessagingBudget $budget, ProviderHealth $health, MessageRenderer $renderer): View
    {
        $actor = $this->actor();

        $paginator = null;
        if ($this->tab === 'campaigns') {
            $query = MessageCampaign::query()->with(['creator:id,name', 'template:id,name'])
                ->withCount([
                    'messages as sent_count' => fn ($q) => $q->whereIn('status', ['sent', 'delivered']),
                    'messages as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
                    'messages as failed_count' => fn ($q) => $q->where('status', 'failed'),
                ])
                ->when(CampaignStatus::tryFrom($this->status), fn ($q, CampaignStatus $s) => $q->where('status', $s))
                ->latest('id');

            // Campaigns are few; visibility depends on the audience scope, so it is checked per campaign.
            $visible = $actor->geoScope('messaging.view')->statewide ? $query->get() : $query->get()->filter(fn (MessageCampaign $c) => $campaigns->canView($c, $actor))->values();
            $page = LengthAwarePaginator::resolveCurrentPage();
            $paginator = new LengthAwarePaginator($visible->forPage($page, 20)->values(), $visible->count(), 20, $page, ['path' => request()->url(), 'pageName' => 'page']);
        }

        $templates = $this->tab === 'templates' ? MessageTemplate::query()->with('creator:id,name')->orderBy('status')->orderBy('name')->get() : collect();

        return view('livewire.workspace.messaging.messaging-index', [
            'campaignList' => $paginator,
            'templates' => $templates,
            'statuses' => CampaignStatus::options(),
            'budget' => ['limit' => $budget->limit(), 'spent' => $budget->spentThisMonth()],
            'health' => $this->tab === 'health' ? ['sms' => $health->summary(ConsentChannel::Sms), 'whatsapp' => $health->summary(ConsentChannel::Whatsapp)] : [],
            'providers' => ['sms' => (string) config('campaign.sms.driver'), 'whatsapp' => (string) config('services.whatsapp.driver')],
            'purposes' => ConsentPurpose::options(),
            'renderer' => $renderer,
        ])->title(__('messaging.title'))->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('messaging.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
