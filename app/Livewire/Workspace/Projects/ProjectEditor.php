<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Projects;

use App\Enums\ProjectVerification;
use App\Models\Lga;
use App\Models\Media;
use App\Models\Project;
use App\Models\User;
use App\Models\Ward;
use App\Services\Content\MediaService;
use App\Services\Projects\ProjectService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class ProjectEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $projectId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'community';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public string $location = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $sourceNote = '';

    public string $verification = 'unverified';

    public ?int $coverId = null;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?Project $project = null): void
    {
        $this->authorize('projects.manage');

        if ($project?->exists) {
            $this->projectId = $project->id;
            $this->fill(['title' => $project->title, 'description' => $project->description, 'category' => $project->category, 'lgaId' => $project->lga_id, 'wardId' => $project->ward_id,
                'location' => (string) $project->location, 'startDate' => $project->start_date?->toDateString() ?? '', 'endDate' => $project->end_date?->toDateString() ?? '',
                'sourceNote' => $project->source_note, 'verification' => $project->verification->value, 'coverId' => $project->cover_media_id]);
        }
    }

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function updatedImage(MediaService $media): void
    {
        $this->validate(['image' => ['image', 'max:8192', 'mimes:jpg,jpeg,png,webp']]);
        $user = Auth::user();
        assert($user instanceof User);
        $this->coverId = $media->storeImage($this->image, $user, $this->title)->id;
        $this->reset('image');
    }

    public function save(ProjectService $projects): void
    {
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'max:20000'],
            'category' => [Rule::in(Project::CATEGORIES)],
            'lgaId' => ['nullable', 'integer'],
            'wardId' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:200'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'sourceNote' => ['required', 'string', 'min:5', 'max:500'],
            'verification' => [Rule::enum(ProjectVerification::class)],
        ], [], ['sourceNote' => __('projects.source_note')]);

        $user = Auth::user();
        assert($user instanceof User);

        $project = $projects->save($user, [
            'title' => $this->title, 'description' => $this->description, 'category' => $this->category, 'lga_id' => $this->lgaId, 'ward_id' => $this->wardId,
            'location' => $this->location, 'start_date' => $this->startDate ?: null, 'end_date' => $this->endDate ?: null,
            'source_note' => $this->sourceNote, 'verification' => $this->verification, 'cover_media_id' => $this->coverId,
        ], $this->projectId ? Project::query()->findOrFail($this->projectId) : null);

        session()->flash('success', __('projects.saved'));
        $this->redirectRoute('workspace.projects.show', $project);
    }

    public function render(): View
    {
        return view('livewire.workspace.projects.project-editor', [
            'lgas' => Lga::query()->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lgaId ? Ward::query()->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'categories' => collect(Project::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __('projects.category_'.$c)])->all(),
            'cover' => $this->coverId ? Media::query()->find($this->coverId) : null,
        ])->title($this->projectId ? __('projects.edit') : __('projects.new'))
            ->layoutData(['breadcrumbs' => [__('content.title') => route('workspace.content.index'), __('projects.title') => route('workspace.projects.index'), ($this->projectId ? __('projects.edit') : __('projects.new')) => null]]);
    }
}
