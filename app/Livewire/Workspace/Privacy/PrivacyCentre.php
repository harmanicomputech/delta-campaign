<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Privacy;

use App\Enums\ConsentChannel;
use App\Enums\DsrStatus;
use App\Enums\DsrType;
use App\Enums\RequestChannel;
use App\Enums\SuppressionReason;
use App\Livewire\Concerns\WithWorkspacePagination;
use App\Models\DataSubjectRequest;
use App\Models\PrivacyNotice;
use App\Models\Supporter;
use App\Models\Suppression;
use App\Models\User;
use App\Rules\NigerianMobileNumber;
use App\Services\Audit\AuditLogger;
use App\Services\Consent\SuppressionService;
use App\Services\Privacy\DataSubjectRequestService;
use App\Services\Privacy\RetentionService;
use App\Services\Settings\Settings;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Data Protection Officer's workspace: requests, suppression list,
 * privacy-notice versions and retention.
 */
final class PrivacyCentre extends Component
{
    use WithWorkspacePagination;

    #[Url(except: 'requests')]
    public string $tab = 'requests';

    #[Url(except: 'open')]
    public string $requestFilter = 'open';

    // Record a request
    public string $dsrType = 'access';

    public string $dsrPhone = '';

    public string $dsrDetails = '';

    public bool $dsrIdentityChecked = false;

    // Suppression lookup
    public string $lookupPhone = '';

    public ?string $lookupHash = null;

    public string $suppressChannel = '';

    public string $liftReason = '';

    // Notice
    public string $noticeVersion = '';

    public string $noticeTitle = 'Privacy notice';

    public string $summaryEn = '';

    public string $summaryPcm = '';

    public string $bodyEn = '';

    public string $bodyPcm = '';

    public ?int $retentionPreview = null;

    public function mount(): void
    {
        abort_unless($this->actor()->can('privacy.manage') || $this->actor()->can('dsr.manage'), 403);
        $this->tab = in_array($this->tab, ['requests', 'suppressions', 'notices', 'retention'], true) ? $this->tab : 'requests';
    }

    public function updatedRequestFilter(): void
    {
        $this->resetPage();
    }

    public function recordRequest(DataSubjectRequestService $service): void
    {
        $this->authorize('dsr.manage');
        $this->validate([
            'dsrType' => [Rule::enum(DsrType::class)],
            'dsrPhone' => ['required', 'string', new NigerianMobileNumber],
            'dsrDetails' => ['nullable', 'string', 'max:2000'],
        ], [], ['dsrPhone' => 'phone number']);

        $request = $service->submit(DsrType::from($this->dsrType), $this->dsrPhone, RequestChannel::Staff, $this->dsrDetails ?: null, $this->dsrIdentityChecked, $this->actor());

        $this->reset('dsrPhone', 'dsrDetails', 'dsrIdentityChecked');
        $this->dispatch('close-modal', name: 'record-request');
        $this->redirectRoute('workspace.privacy.requests.show', $request);
    }

    public function lookup(): void
    {
        $this->authorize('privacy.manage');
        $this->validate(['lookupPhone' => ['required', 'string', new NigerianMobileNumber]], [], ['lookupPhone' => 'phone number']);
        $this->lookupHash = PhoneHasher::fromInput($this->lookupPhone);
        app(AuditLogger::class)->record('suppression.looked_up', actor: $this->actor());
    }

    public function suppress(SuppressionService $suppressions): void
    {
        $this->authorize('privacy.manage');
        abort_if($this->lookupHash === null, 422);
        $suppressions->suppress($this->lookupHash, ConsentChannel::tryFrom($this->suppressChannel), SuppressionReason::Admin, RequestChannel::Staff, $this->actor(), 'Added by the Data Protection Officer');
        $this->dispatch('toast', type: 'success', title: __('privacy.added'));
    }

    public function lift(int $id, SuppressionService $suppressions): void
    {
        $this->authorize('privacy.manage');
        $this->validate(['liftReason' => ['required', 'string', 'min:5', 'max:300']], [], ['liftReason' => 'reason']);
        $suppression = Suppression::query()->whereNull('lifted_at')->findOrFail($id);

        if (in_array($suppression->reason, [SuppressionReason::Erasure, SuppressionReason::OptOut], true)) {
            $this->dispatch('toast', type: 'warning', title: __('users.not_allowed'), message: 'Opt-outs and erasure suppressions are lifted only by the person\'s own new consent.');

            return;
        }

        $suppressions->lift($suppression, $this->actor(), $this->liftReason);
        $this->reset('liftReason');
        $this->dispatch('toast', type: 'success', title: __('privacy.lifted'));
    }

    public function saveNotice(AuditLogger $audit): void
    {
        $this->authorize('privacy.manage');
        $this->validate([
            'noticeVersion' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/', 'unique:privacy_notices,version'],
            'noticeTitle' => ['required', 'string', 'max:200'],
            'summaryEn' => ['required', 'string', 'max:2000'],
            'summaryPcm' => ['nullable', 'string', 'max:2000'],
            'bodyEn' => ['required', 'string', 'max:50000'],
            'bodyPcm' => ['nullable', 'string', 'max:50000'],
        ], [], ['noticeVersion' => 'version']);

        $notice = PrivacyNotice::query()->create([
            'version' => $this->noticeVersion, 'title' => $this->noticeTitle,
            'summary_en' => $this->summaryEn, 'summary_pcm' => $this->summaryPcm ?: null,
            'body_en' => $this->bodyEn, 'body_pcm' => $this->bodyPcm ?: null,
        ]);
        $notice->forceFill(['created_by' => $this->actor()->id])->save();
        $audit->record('privacy_notice.drafted', $notice, actor: $this->actor());

        $this->reset('noticeVersion', 'summaryEn', 'summaryPcm', 'bodyEn', 'bodyPcm');
        $this->dispatch('close-modal', name: 'new-notice');
        $this->dispatch('toast', type: 'success', title: __('privacy.notice_saved'));
    }

    public function publishNotice(int $id, AuditLogger $audit): void
    {
        $this->authorize('privacy.manage');
        $notice = PrivacyNotice::query()->whereNull('published_at')->findOrFail($id);
        $notice->forceFill(['published_at' => now(), 'published_by' => $this->actor()->id])->save();
        $audit->record('privacy_notice.published', $notice, actor: $this->actor());
        $this->dispatch('toast', type: 'success', title: __('privacy.notice_published'));
    }

    public function previewRetention(RetentionService $retention): void
    {
        $this->authorize('privacy.manage');
        $this->retentionPreview = $retention->due()->count();
    }

    public function render(): View
    {
        $requests = DataSubjectRequest::query()
            ->when($this->requestFilter === 'open', fn ($q) => $q->whereNotIn('status', [DsrStatus::Completed, DsrStatus::Rejected]))
            ->when($this->requestFilter === 'overdue', fn ($q) => $q->whereNotIn('status', [DsrStatus::Completed, DsrStatus::Rejected])->where('due_at', '<', now()))
            ->orderBy('due_at')->paginate(20, pageName: 'requests');

        return view('livewire.workspace.privacy.privacy-centre', [
            'requests' => $requests,
            'openCount' => DataSubjectRequest::query()->whereNotIn('status', [DsrStatus::Completed, DsrStatus::Rejected])->count(),
            'overdueCount' => DataSubjectRequest::query()->whereNotIn('status', [DsrStatus::Completed, DsrStatus::Rejected])->where('due_at', '<', now())->count(),
            'activeSuppressions' => Suppression::query()->whereNull('lifted_at')->count(),
            'lookupResults' => $this->lookupHash ? Suppression::query()->where('phone_hash', $this->lookupHash)->latest()->get() : collect(),
            'recentSuppressions' => Suppression::query()->whereNull('lifted_at')->latest()->limit(20)->get(),
            'notices' => PrivacyNotice::query()->orderByDesc('created_at')->get(),
            'currentNotice' => PrivacyNotice::current(),
            'legalHolds' => Supporter::query()->where('legal_hold', true)->whereNull('erased_at')->count(),
            'types' => DsrType::options(),
            'channels' => ['' => __('privacy.all_channels')] + ConsentChannel::options(),
            'retentionMonths' => (int) app(Settings::class)->get('privacy.retention_months'),
        ])->title(__('privacy.title'))->layoutData(['breadcrumbs' => [__('nav.groups.administration') => null, __('privacy.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
