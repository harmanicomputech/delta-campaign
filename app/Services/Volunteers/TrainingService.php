<?php

declare(strict_types=1);

namespace App\Services\Volunteers;

use App\Models\QuizQuestion;
use App\Models\TrainingAttempt;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Training modules with short quizzes. Content is written by the campaign;
 * the platform ships only clearly-marked SAMPLE modules.
 */
final class TrainingService
{
    public const array AUDIENCES = ['all', 'volunteer', 'polling_agent'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title: string, summary: string, body: string, audience: string, language: string, pass_mark: int}  $data
     */
    public function saveModule(User $by, array $data, ?TrainingModule $module = null): TrainingModule
    {
        $this->assertCanManage($by);

        $attributes = [
            'title' => mb_substr($data['title'], 0, 200),
            'summary' => mb_substr($data['summary'], 0, 500),
            'body' => $data['body'],
            'audience' => in_array($data['audience'], self::AUDIENCES, true) ? $data['audience'] : 'all',
            'language' => in_array($data['language'], ['en', 'pcm'], true) ? $data['language'] : 'en',
            'pass_mark' => max(0, min(100, (int) $data['pass_mark'])),
        ];

        if ($module === null) {
            $slug = Str::slug($attributes['title']) ?: 'module';
            $base = $slug;
            for ($i = 2; TrainingModule::query()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }

            $module = TrainingModule::query()->create($attributes + ['slug' => $slug, 'created_by' => $by->id, 'sort' => (int) TrainingModule::query()->max('sort') + 1]);
            $this->audit->record('training.module_created', $module, null, [], $by);

            return $module;
        }

        $module->fill($attributes)->save();
        $this->audit->recordChanges('training.module_updated', $module);

        return $module;
    }

    /** @param list<string> $options */
    public function addQuestion(User $by, TrainingModule $module, string $prompt, array $options, int $correctIndex): QuizQuestion
    {
        $this->assertCanManage($by);

        $options = array_values(array_filter(array_map(fn ($o) => mb_substr(trim((string) $o), 0, 200), $options), fn ($o) => $o !== ''));

        if (count($options) < 2 || count($options) > 6) {
            throw ValidationException::withMessages(['options' => __('training.options_count')]);
        }
        if ($correctIndex < 0 || $correctIndex >= count($options)) {
            throw ValidationException::withMessages(['correctIndex' => __('training.correct_invalid')]);
        }

        return $module->questions()->create([
            'prompt' => mb_substr(trim($prompt), 0, 500),
            'options' => $options,
            'correct_index' => $correctIndex,
            'sort' => (int) $module->questions()->max('sort') + 1,
        ]);
    }

    public function removeQuestion(User $by, QuizQuestion $question): void
    {
        $this->assertCanManage($by);
        $question->delete();
    }

    public function setPublished(User $by, TrainingModule $module, bool $published): void
    {
        $this->assertCanManage($by);
        $module->forceFill(['published_at' => $published ? ($module->published_at ?? now()) : null])->save();
        $this->audit->record($published ? 'training.module_published' : 'training.module_unpublished', $module, null, [], $by);
    }

    public function canTake(User $user, TrainingModule $module): bool
    {
        return $module->isPublished() && $user->hasPermission('training.take') && in_array($module->audience, $this->audiencesFor($user), true);
    }

    private function assertCanManage(User $by): void
    {
        if (! $by->hasPermission('training.manage')) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }

    /**
     * Scores a quiz. Answers are question id => chosen option index; unanswered questions score zero.
     *
     * @param  array<int|string, int|string>  $answers
     */
    public function submit(TrainingModule $module, User $user, array $answers): TrainingAttempt
    {
        if (! $module->isPublished()) {
            throw ValidationException::withMessages(['module' => __('training.not_published')]);
        }
        if (! $this->canTake($user, $module)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }

        $questions = $module->questions()->get()->makeVisible('correct_index');
        $correct = $questions->filter(fn ($q) => isset($answers[$q->id]) && (int) $answers[$q->id] === $q->correct_index)->count();
        $score = $questions->isEmpty() ? 100 : (int) round($correct / $questions->count() * 100);

        return TrainingAttempt::query()->create([
            'training_module_id' => $module->id,
            'user_id' => $user->id,
            'score' => $score,
            'passed' => $score >= $module->pass_mark,
            'answers' => collect($answers)->only($questions->pluck('id'))->map(fn ($v) => (int) $v)->all(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Best result per module for a user.
     *
     * @return Collection<int, TrainingAttempt> keyed by module id
     */
    public function bestResults(User $user): Collection
    {
        return TrainingAttempt::query()->where('user_id', $user->id)
            ->orderByDesc('passed')->orderByDesc('score')->get()
            ->unique('training_module_id')->keyBy('training_module_id');
    }

    /**
     * Modules a role should see: "all", plus the audience matching the person's roles.
     *
     * @return list<string>
     */
    public function audiencesFor(User $user): array
    {
        $audiences = ['all'];
        if ($user->hasRole('volunteer') || $user->hasRole('ward_coordinator')) {
            $audiences[] = 'volunteer';
        }
        if ($user->hasRole('polling_agent')) {
            $audiences[] = 'polling_agent';
        }

        return $user->hasPermission('training.manage') ? ['all', 'volunteer', 'polling_agent'] : $audiences;
    }
}
