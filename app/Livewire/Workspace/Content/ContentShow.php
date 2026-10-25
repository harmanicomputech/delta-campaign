<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Content;

use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Content\ContentService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ContentShow extends Component
{
    #[Locked]
    public ContentItem $item;

    public string $note = '';

    public string $publishAt = '';

    public string $previewLang = 'en';

    public function mount(ContentItem $item): void
    {
        $this->authorize('content.view');
        $this->item = $item;
    }

    public function submit(ContentService $content): void
    {
        $this->act(fn () => $content->submit($this->item, $this->actor()), __('content.submitted'));
    }

    public function approve(ContentService $content): void
    {
        $this->act(fn () => $content->approve($this->item, $this->actor()), __('content.approved_ok'));
    }

    public function reject(ContentService $content): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:500']], [], ['note' => __('content.review_note')]);
        $this->act(fn () => $content->reject($this->item, $this->actor(), $this->note), __('content.rejected_ok'));
        $this->dispatch('close-modal', name: 'reject-content');
        $this->reset('note');
    }

    public function publish(ContentService $content): void
    {
        $this->validate(['publishAt' => ['nullable', 'date']]);
        $at = $this->publishAt !== '' ? Carbon::parse($this->publishAt, config('campaign.display_timezone'))->utc() : null;
        $this->act(fn () => $content->publish($this->item, $this->actor(), $at), $at && $at->isFuture() ? __('content.scheduled_ok') : __('content.published_ok'));
        $this->dispatch('close-modal', name: 'publish-content');
    }

    public function withdraw(ContentService $content): void
    {
        $this->validate(['note' => ['required', 'string', 'min:3', 'max:500']], [], ['note' => __('volunteers.reason')]);
        $this->act(fn () => $content->withdraw($this->item, $this->actor(), $this->note), __('content.withdrawn_ok'));
        $this->dispatch('close-modal', name: 'withdraw-content');
        $this->reset('note');
    }

    public function archive(ContentService $content): void
    {
        $this->act(fn () => $content->archive($this->item, $this->actor()), __('content.archived_ok'));
    }

    public function restore(int $versionId, ContentService $content): void
    {
        $version = ContentVersion::query()->where('content_item_id', $this->item->id)->findOrFail($versionId);
        $this->act(fn () => $content->restore($this->item, $version, $this->actor()), __('content.restored_ok', ['version' => $version->version]));
    }

    private function act(callable $action, string $success): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', title: collect($e->errors())->flatten()->first());

            return;
        } catch (AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->item->refresh();
        $this->dispatch('toast', type: 'success', title: $success);
    }

    public function render(): View
    {
        $item = $this->item->load(['creator:id,name', 'approver:id,name', 'cover', 'lga:id,name']);

        return view('livewire.workspace.content.content-show', [
            'versions' => $item->versions()->with('user:id,name')->latest('version')->limit(15)->get(),
            'history' => WorkflowTransition::query()->where('subject_type', $item->getMorphClass())->where('subject_id', $item->id)->with('user:id,name')->latest('id')->limit(20)->get(),
            'isAuthor' => $item->created_by === $this->actor()->id,
            'body' => $this->previewLang === 'pcm' && filled($item->body_pcm) ? $item->body_pcm : $item->body_en,
            'title' => $this->previewLang === 'pcm' && filled($item->title_pcm) ? $item->title_pcm : $item->title_en,
        ])->title($item->title_en)->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('content.title') => route('workspace.content.index', ['type' => $item->type->value]), $item->title_en => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
