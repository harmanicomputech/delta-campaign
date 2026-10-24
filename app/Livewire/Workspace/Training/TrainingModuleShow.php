<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Training;

use App\Models\QuizQuestion;
use App\Models\TrainingAttempt;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\Volunteers\TrainingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class TrainingModuleShow extends Component
{
    #[Locked]
    public TrainingModule $module;

    /** @var array<int|string, int|string> question id => option index */
    public array $answers = [];

    #[Locked]
    public ?int $attemptId = null;

    // Module editing
    public string $title = '';

    public string $summary = '';

    public string $body = '';

    public string $audience = 'all';

    public string $language = 'en';

    public int $passMark = 70;

    // New question
    public string $prompt = '';

    public string $options = '';

    public int $correctIndex = 0;

    public function mount(TrainingModule $module, TrainingService $training): void
    {
        $actor = $this->actor();
        $manager = $actor->can('training.manage');

        abort_unless($manager || ($module->isPublished() && $actor->can('training.take') && in_array($module->audience, $training->audiencesFor($actor), true)), 404);

        $this->module = $module;
        $this->fill(['title' => $module->title, 'summary' => $module->summary, 'body' => $module->body, 'audience' => $module->audience, 'language' => $module->language, 'passMark' => $module->pass_mark]);
    }

    public function submit(TrainingService $training): void
    {
        $questions = $this->module->questions()->pluck('id');
        $this->validate(['answers' => ['array'], 'answers.*' => ['integer', 'min:0']]);

        if ($questions->diff(array_keys($this->answers))->isNotEmpty()) {
            $this->addError('answers', __('training.answer_all'));

            return;
        }

        $attempt = $training->submit($this->module, $this->actor(), $this->answers);
        $this->attemptId = $attempt->id;
        $this->dispatch('toast', type: $attempt->passed ? 'success' : 'warning', title: $attempt->passed ? __('training.passed_toast', ['score' => $attempt->score]) : __('training.failed_toast', ['score' => $attempt->score, 'mark' => $this->module->pass_mark]));
    }

    public function retry(): void
    {
        $this->reset('answers', 'attemptId');
    }

    public function save(TrainingService $training): void
    {
        $this->authorize('training.manage');
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'summary' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => [Rule::in(TrainingService::AUDIENCES)],
            'language' => [Rule::in(['en', 'pcm'])],
            'passMark' => ['integer', 'between:0,100'],
        ]);
        $training->saveModule($this->actor(), ['title' => $this->title, 'summary' => $this->summary, 'body' => $this->body, 'audience' => $this->audience, 'language' => $this->language, 'pass_mark' => $this->passMark], $this->module);

        $this->module->refresh();
        $this->dispatch('close-modal', name: 'edit-module');
        $this->dispatch('toast', type: 'success', title: __('ui.saved'));
    }

    public function addQuestion(TrainingService $training): void
    {
        $this->authorize('training.manage');
        $this->validate(['prompt' => ['required', 'string', 'min:3', 'max:500'], 'options' => ['required', 'string', 'max:1500'], 'correctIndex' => ['integer', 'min:0']], [], ['prompt' => __('training.question')]);
        $training->addQuestion($this->actor(), $this->module, $this->prompt, preg_split('/\R/', $this->options) ?: [], $this->correctIndex);

        $this->reset('prompt', 'options', 'correctIndex');
        $this->dispatch('close-modal', name: 'add-question');
        $this->dispatch('toast', type: 'success', title: __('training.question_added'));
    }

    public function removeQuestion(int $id, TrainingService $training): void
    {
        $this->authorize('training.manage');
        $training->removeQuestion($this->actor(), QuizQuestion::query()->where('training_module_id', $this->module->id)->findOrFail($id));
        unset($this->answers[$id]);
    }

    public function togglePublished(TrainingService $training): void
    {
        $this->authorize('training.manage');
        $publish = ! $this->module->isPublished();

        if ($publish && $this->module->questions()->doesntExist()) {
            $this->dispatch('toast', type: 'warning', title: __('training.needs_questions'));

            return;
        }

        $training->setPublished($this->actor(), $this->module, $publish);
        $this->module->refresh();
        $this->dispatch('toast', type: 'success', title: $publish ? __('training.published_ok') : __('training.unpublished_ok'));
    }

    public function render(TrainingService $training): View
    {
        $actor = $this->actor();
        $questions = $this->module->questions()->orderBy('sort')->get();

        if ($actor->can('training.manage')) {
            $questions->makeVisible('correct_index');
        }

        return view('livewire.workspace.training.training-module-show', [
            'questions' => $questions,
            'attempt' => $this->attemptId ? TrainingAttempt::query()->where('user_id', $actor->id)->find($this->attemptId) : null,
            'best' => $training->bestResults($actor)->get($this->module->id),
            'canTake' => $training->canTake($actor, $this->module),
            'html' => str($this->module->body)->markdown(['html_input' => 'strip', 'allow_unsafe_links' => false])->toString(),
            'pending' => array_filter(explode("\n", str_replace("\r", '', $this->options)), fn ($o) => trim($o) !== ''),
        ])->title($this->module->title)->layoutData(['breadcrumbs' => [__('nav.groups.people') => null, __('training.title') => route('workspace.training.index'), $this->module->title => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
