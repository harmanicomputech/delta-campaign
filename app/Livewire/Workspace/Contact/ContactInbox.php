<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Contact;

use App\Enums\ContactStatus;
use App\Livewire\Concerns\WithTableState;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Messages from the public contact form. Bodies and contact details are
 * encrypted at rest and only decrypted when a message is opened; each opening
 * is audited.
 */
final class ContactInbox extends Component
{
    use WithTableState;

    #[Url(except: 'new')]
    public string $status = 'new';

    #[Url(except: '')]
    public string $topic = '';

    #[Locked]
    public ?int $openId = null;

    public function mount(): void
    {
        $this->authorize('contact.manage');
    }

    protected function sortableColumns(): array
    {
        return ['created_at'];
    }

    public function openMessage(int $id, AuditLogger $audit): void
    {
        $message = ContactMessage::query()->findOrFail($id);
        $this->openId = $message->id;
        $audit->record('contact.viewed', $message, null, [], $this->actor());
        $this->dispatch('open-modal', name: 'contact-message');
    }

    public function setStatus(string $status, AuditLogger $audit): void
    {
        validator(['status' => $status], ['status' => [Rule::enum(ContactStatus::class)]])->validate();
        $message = ContactMessage::query()->findOrFail($this->openId);
        $message->forceFill(['status' => $status, 'handled_by' => $this->actor()->id, 'handled_at' => now()])->save();
        $audit->record('contact.'.$status, $message, null, [], $this->actor());

        $this->dispatch('close-modal', name: 'contact-message');
        $this->dispatch('toast', type: 'success', title: __('contact.marked', ['status' => ContactStatus::from($status)->label()]));
        $this->openId = null;
    }

    public function render(): View
    {
        $query = ContactMessage::query()->with('lga:id,name')
            ->when(ContactStatus::tryFrom($this->status), fn ($q, ContactStatus $s) => $q->where('status', $s))
            ->when(in_array($this->topic, ContactMessage::TOPICS, true), fn ($q) => $q->where('topic', $this->topic))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('reference', 'ilike', $this->likeTerm())->orWhere('name', 'ilike', $this->likeTerm())));

        return view('livewire.workspace.contact.contact-inbox', [
            'messages' => $this->applySort($query, 'created_at', 'desc')->paginate($this->perPage),
            'open' => $this->openId ? ContactMessage::query()->with(['lga:id,name', 'handler:id,name'])->find($this->openId) : null,
            'statuses' => ContactStatus::options(),
        ])->title(__('contact.title'))->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('contact.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
