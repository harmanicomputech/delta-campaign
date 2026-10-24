<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOperations;

use App\Livewire\Workspace\Training\TrainingModuleShow;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\Users\AuthorizationDenied;
use App\Services\Volunteers\TrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class TrainingTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private TrainingService $training;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->training = app(TrainingService::class);
        $this->director = User::factory()->withRole('state_director')->create();
    }

    private function module(string $audience = 'volunteer', bool $publish = true): TrainingModule
    {
        $module = $this->training->saveModule($this->director, ['title' => 'Sample module', 'summary' => 'Sample', 'body' => "## Heading\n\n<script>alert(1)</script> Text", 'audience' => $audience, 'language' => 'en', 'pass_mark' => 60]);
        $this->training->addQuestion($this->director, $module, 'Q1', ['A', 'B', 'C'], 1);
        $this->training->addQuestion($this->director, $module, 'Q2', ['Yes', 'No'], 0);
        if ($publish) {
            $this->training->setPublished($this->director, $module, true);
        }

        return $module->refresh();
    }

    public function test_quizzes_are_scored_against_the_pass_mark(): void
    {
        $module = $this->module();
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        [$q1, $q2] = $module->questions()->orderBy('sort')->pluck('id')->all();

        $fail = $this->training->submit($module, $volunteer, [$q1 => 1, $q2 => 1]);
        $pass = $this->training->submit($module, $volunteer, [$q1 => 1, $q2 => 0]);

        $this->assertSame(50, $fail->score);
        $this->assertFalse($fail->passed);
        $this->assertSame(100, $pass->score);
        $this->assertTrue($pass->passed);
        $this->assertTrue($this->training->bestResults($volunteer)->get($module->id)->is($pass));
    }

    public function test_drafts_and_other_audiences_cannot_be_taken(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        try {
            $this->training->submit($this->module(publish: false), $volunteer, []);
            $this->fail('Draft accepted');
        } catch (ValidationException) {
        }

        $this->expectException(AuthorizationDenied::class);
        $this->training->submit($this->module('polling_agent'), $volunteer, []);
    }

    public function test_only_training_managers_can_author_modules(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $this->expectException(AuthorizationDenied::class);
        $this->training->saveModule($volunteer, ['title' => 'Nope', 'summary' => 'x', 'body' => 'x', 'audience' => 'all', 'language' => 'en', 'pass_mark' => 50]);
    }

    public function test_question_validation(): void
    {
        $module = $this->module();

        $this->expectException(ValidationException::class);
        $this->training->addQuestion($this->director, $module, 'Bad', ['Only one'], 0);
    }

    public function test_the_quiz_page_never_reveals_answers_to_learners_and_strips_html(): void
    {
        $module = $this->module();
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $response = $this->signIn($volunteer)->get(route('workspace.training.show', $module))->assertOk();
        $response->assertDontSee('correct_index')->assertDontSee('<script>alert(1)</script>', false)->assertSee('<h2>Heading</h2>', false);
        $this->assertStringNotContainsString(__('training.correct'), (string) $response->getContent());

        Livewire::actingAs($volunteer)->test(TrainingModuleShow::class, ['module' => $module])
            ->set('answers', [$module->questions()->first()->id => 1])
            ->call('submit')->assertHasErrors('answers');
    }

    public function test_learners_cannot_open_drafts_or_other_audiences(): void
    {
        $volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();

        $this->signIn($volunteer)->get(route('workspace.training.show', $this->module(publish: false)))->assertNotFound();
        $this->signIn($volunteer)->get(route('workspace.training.show', $this->module('polling_agent')))->assertNotFound();
        $this->signIn($volunteer)->get(route('workspace.training.index'))->assertOk();
    }

    public function test_people_without_training_permissions_are_refused(): void
    {
        $analyst = User::factory()->withRole('analyst')->create();

        $this->signIn($analyst)->get(route('workspace.training.index'))->assertForbidden();
    }
}
