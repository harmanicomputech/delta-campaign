<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Privacy;

use App\Enums\DsrType;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\DataSubjectRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RequestShow extends Component
{
    #[Locked]
    public DataSubjectRequest $request;

    public string $verificationMethod = '';

    public string $resolution = '';

    public string $rejection = '';

    public function mount(DataSubjectRequest $request): void
    {
        $this->authorize('dsr.manage');
        $this->request = $request;
    }

    public function verifyIdentity(DataSubjectRequestService $service): void
    {
        $this->validate(['verificationMethod' => ['required', 'string', 'min:3', 'max:40']], [], ['verificationMethod' => 'method']);
        $service->markIdentityVerified($this->request, $this->actor(), $this->verificationMethod);
        $this->request->refresh();
        $this->dispatch('close-modal', name: 'verify-identity');
        $this->dispatch('toast', type: 'success', title: __('privacy.identity_verified'));
    }

    public function preparePackage(DataSubjectRequestService $service): void
    {
        $service->prepareAccessPackage($this->request, $this->actor());
        $this->request->refresh();
        $this->dispatch('toast', type: 'success', title: __('privacy.package_ready'));
    }

    public function downloadPackage(AuditLogger $audit): StreamedResponse
    {
        abort_unless($this->request->export_path, 404);
        $audit->record('dsr.access_package_downloaded', $this->request, actor: $this->actor());

        return Storage::disk((string) config('campaign.privacy.exports_disk'))->download($this->request->export_path, $this->request->reference.'.json');
    }

    public function complete(DataSubjectRequestService $service): void
    {
        $this->validate(['resolution' => ['required', 'string', 'min:10', 'max:2000']]);

        try {
            $service->complete($this->request, $this->actor(), $this->resolution);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'danger', title: $e->getMessage());

            return;
        }

        $this->request->refresh();
        $this->dispatch('close-modal', name: 'complete-request');
        $this->dispatch('toast', type: 'success', title: __('privacy.completed'));
    }

    public function reject(DataSubjectRequestService $service): void
    {
        $this->validate(['rejection' => ['required', 'string', 'min:10', 'max:300']], [], ['rejection' => 'reason']);
        $service->reject($this->request, $this->actor(), $this->rejection);
        $this->request->refresh();
        $this->dispatch('close-modal', name: 'reject-request');
        $this->dispatch('toast', type: 'success', title: __('privacy.rejected'));
    }

    public function render(): View
    {
        $this->request->load(['supporter', 'transitions.user']);

        return view('livewire.workspace.privacy.request-show', [
            'isAccess' => $this->request->type === DsrType::Access,
            'isErasure' => $this->request->type === DsrType::Erasure,
            'stopsProcessing' => in_array($this->request->type, [DsrType::WithdrawConsent, DsrType::Objection, DsrType::Restriction, DsrType::Erasure], true),
        ])->title($this->request->reference)->layoutData(['breadcrumbs' => [
            __('privacy.title') => route('workspace.privacy.index'),
            $this->request->reference => null,
        ]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
