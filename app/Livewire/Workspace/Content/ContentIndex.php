<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Content;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Livewire\Concerns\WithTableState;
use App\Models\ContentItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

final class ContentIndex extends Component
{
    use WithTableState;

    #[Url(except: 'news')]
    public string $type = 'news';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('content.view');
        if (ContentType::tryFrom($this->type) === null) {
            $this->type = 'news';
        }
    }

    protected function sortableColumns(): array
    {
        return ['title_en', 'updated_at'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $query = ContentItem::query()->where('type', $this->type)->with(['creator:id,name', 'lga:id,name'])
            ->when(ContentStatus::tryFrom($this->status), fn ($q, ContentStatus $s) => $q->where('status', $s))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title_en', 'ilike', $this->likeTerm()));

        return view('livewire.workspace.content.content-index', [
            'items' => $this->applySort($query, 'updated_at', 'desc')->paginate($this->perPage),
            'types' => ContentType::options(),
            'statuses' => ContentStatus::options(),
            'reviewCount' => ContentItem::query()->where('status', ContentStatus::InReview)->count(),
        ])->title(__('content.title'))->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('content.title') => null]]);
    }
}
