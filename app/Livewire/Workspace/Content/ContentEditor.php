<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Content;

use App\Enums\ContentType;
use App\Models\ContentItem;
use App\Models\Lga;
use App\Models\Media;
use App\Models\User;
use App\Services\Content\ContentService;
use App\Services\Content\MediaService;
use App\Services\Supporters\StaleRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class ContentEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $itemId = null;

    #[Locked]
    public int $loadedVersion = 1;

    #[Url(except: 'news')]
    public string $type = 'news';

    public string $titleEn = '';

    public string $titlePcm = '';

    public string $summaryEn = '';

    public string $summaryPcm = '';

    public string $bodyEn = '';

    public string $bodyPcm = '';

    public string $slug = '';

    public ?int $lgaId = null;

    public ?int $coverId = null;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public string $altEn = '';

    public string $note = '';

    public function mount(?ContentItem $item = null): void
    {
        $this->authorize('content.create');

        if ($item?->exists) {
            $this->itemId = $item->id;
            $this->loadedVersion = $item->version;
            $this->type = $item->type->value;
            $this->fill(['titleEn' => $item->title_en, 'titlePcm' => (string) $item->title_pcm, 'summaryEn' => (string) $item->summary_en, 'summaryPcm' => (string) $item->summary_pcm,
                'bodyEn' => $item->body_en, 'bodyPcm' => (string) $item->body_pcm, 'slug' => $item->slug, 'lgaId' => $item->lga_id, 'coverId' => $item->cover_media_id]);
        } elseif (ContentType::tryFrom($this->type) === null) {
            $this->type = 'news';
        }
    }

    public function updatedImage(MediaService $media): void
    {
        $this->validate(['image' => ['image', 'max:8192', 'mimes:jpg,jpeg,png,webp']], [], ['image' => __('content.cover')]);
        $this->coverId = $media->storeImage($this->image, $this->actor(), $this->altEn !== '' ? $this->altEn : $this->titleEn)->id;
        $this->reset('image');
    }

    public function save(ContentService $content, bool $submit = false): void
    {
        $this->validate([
            'titleEn' => ['required', 'string', 'min:3', 'max:200'],
            'titlePcm' => ['nullable', 'string', 'max:200'],
            'summaryEn' => ['nullable', 'string', 'max:500'],
            'summaryPcm' => ['nullable', 'string', 'max:500'],
            'bodyEn' => ['required', 'string', 'max:50000'],
            'bodyPcm' => ['nullable', 'string', 'max:50000'],
            'slug' => ['nullable', 'string', 'max:110', 'regex:/^[a-z0-9-]*$/'],
            'lgaId' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['titleEn' => __('content.title_en'), 'bodyEn' => __('content.body_en')]);

        $data = ['title_en' => $this->titleEn, 'title_pcm' => $this->titlePcm, 'summary_en' => $this->summaryEn, 'summary_pcm' => $this->summaryPcm,
            'body_en' => $this->bodyEn, 'body_pcm' => $this->bodyPcm, 'slug' => $this->slug, 'lga_id' => $this->lgaId, 'cover_media_id' => $this->coverId];

        try {
            $item = $this->itemId
                ? $content->update(ContentItem::query()->findOrFail($this->itemId), $this->actor(), $data, $this->loadedVersion, $this->note !== '' ? $this->note : null)
                : $content->create($this->actor(), ContentType::from($this->type), $data);

            if ($submit) {
                $content->submit($item, $this->actor());
            }
        } catch (StaleRecord) {
            $this->addError('titleEn', __('content.stale'));

            return;
        } catch (ValidationException $e) {
            $this->addError('titleEn', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('success', $submit ? __('content.submitted') : __('content.saved'));
        $this->redirectRoute('workspace.content.show', $item);
    }

    public function submitForReview(ContentService $content): void
    {
        $this->save($content, submit: true);
    }

    public function render(): View
    {
        $cover = $this->coverId ? Media::query()->find($this->coverId) : null;

        return view('livewire.workspace.content.content-editor', [
            'cover' => $cover,
            'lgas' => Lga::query()->orderBy('name')->pluck('name', 'id'),
            'preview' => $this->bodyEn,
        ])->title($this->itemId ? __('content.edit') : __('content.new_'.$this->type))
            ->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('content.title') => route('workspace.content.index', ['type' => $this->type]), ($this->itemId ? __('content.edit') : __('content.new_'.$this->type)) => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
