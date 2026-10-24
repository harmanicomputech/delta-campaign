<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Training;

use App\Models\TrainingModule;
use App\Models\User;
use App\Services\Volunteers\TrainingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class TrainingIndex extends Component
{
    public string $title = '';

    public string $summary = '';

    public string $body = '';

    public string $audience = 'all';

    public string $language = 'en';

    public int $passMark = 70;

    public function mount(): void
    {
        abort_unless($this->actor()->can('training.take') || $this->actor()->can('training.manage'), 403);
    }

    public function create(TrainingService $training): void
    {
        $this->authorize('training.manage');
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'summary' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => [Rule::in(TrainingService::AUDIENCES)],
            'language' => [Rule::in(['en', 'pcm'])],
            'passMark' => ['integer', 'between:0,100'],
        ], [], ['passMark' => __('training.pass_mark')]);

        $module = $training->saveModule($this->actor(), ['title' => $this->title, 'summary' => $this->summary, 'body' => $this->body, 'audience' => $this->audience, 'language' => $this->language, 'pass_mark' => $this->passMark]);

        $this->redirectRoute('workspace.training.show', $module);
    }

    public function render(TrainingService $training): View
    {
        $actor = $this->actor();
        $manager = $actor->can('training.manage');

        $modules = TrainingModule::query()->withCount('questions')
            ->whereIn('audience', $training->audiencesFor($actor))
            ->when(! $manager, fn ($q) => $q->whereNotNull('published_at'))
            ->orderBy('sort')->orderBy('title')->get();

        $results = $training->bestResults($actor);

        return view('livewire.workspace.training.training-index', [
            'modules' => $modules,
            'results' => $results,
            'passed' => $modules->filter(fn ($m) => $m->isPublished() && $results->get($m->id)?->passed)->count(),
            'published' => $modules->filter->isPublished()->count(),
            'manager' => $manager,
        ])->title(__('training.title'))->layoutData(['breadcrumbs' => [__('nav.groups.people') => null, __('training.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
