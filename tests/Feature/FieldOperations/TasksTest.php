<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOperations;

use App\Enums\TaskStatus;
use App\Livewire\Workspace\Notifications\NotificationBell;
use App\Livewire\Workspace\Tasks\TaskShow;
use App\Livewire\Workspace\Tasks\TasksIndex;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskEscalated;
use App\Notifications\TaskOverdue;
use App\Services\Supporters\StaleRecord;
use App\Services\Tasks\TaskService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithAccounts;
use Tests\TestCase;

final class TasksTest extends TestCase
{
    use InteractsWithAccounts, RefreshDatabase;

    private TaskService $tasks;

    private User $wardCoordinator;

    private User $lgaCoordinator;

    private User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->tasks = app(TaskService::class);
        $this->wardCoordinator = User::factory()->withRole('ward_coordinator', $this->ward('udu')->id)->create();
        $this->lgaCoordinator = User::factory()->withRole('lga_coordinator', $this->lga('udu')->id)->create();
        $this->volunteer = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
    }

    private function task(array $overrides = [], ?User $assignee = null): Task
    {
        return $this->tasks->create($this->wardCoordinator, $overrides + ['title' => 'Sample task', 'priority' => 'high', 'ward_id' => $this->ward('udu')->id], $assignee ?? $this->volunteer);
    }

    public function test_tasks_can_only_be_created_inside_the_creators_area(): void
    {
        $task = $this->task();
        $this->assertSame($this->lga('udu')->id, $task->lga_id);
        $this->assertNotNull($task->senatorial_district_id);
        $this->assertSame($this->volunteer->id, $task->assigned_to);
        $this->assertSame(['created', 'assigned'], $task->events()->reorder('id')->pluck('type')->map->value->all());

        try {
            $this->tasks->create($this->wardCoordinator, ['title' => 'Whole LGA', 'lga_id' => $this->lga('udu')->id]);
            $this->fail('A ward coordinator created an LGA-wide task');
        } catch (AuthorizationDenied) {
        }

        $this->expectException(AuthorizationDenied::class);
        $this->tasks->create($this->wardCoordinator, ['title' => 'Statewide']);
    }

    public function test_assignees_must_work_in_the_tasks_area(): void
    {
        $task = $this->task();
        $outsider = User::factory()->withRole('volunteer', $this->ward('sapele')->id)->create();

        $this->expectException(ValidationException::class);
        $this->tasks->assign($task, $outsider, $this->wardCoordinator);
    }

    public function test_status_changes_use_optimistic_locking_and_record_history(): void
    {
        $task = $this->task();

        $started = $this->tasks->changeStatus($task, TaskStatus::InProgress, $this->volunteer, $task->version);

        try {
            $this->tasks->changeStatus($task, TaskStatus::Completed, $this->volunteer, $task->version);
            $this->fail('Stale update accepted');
        } catch (StaleRecord) {
        }

        $done = $this->tasks->changeStatus($started, TaskStatus::Completed, $this->volunteer, $started->version, 'Venue confirmed');
        $this->assertSame(TaskStatus::Completed, $done->status);
        $this->assertNotNull($done->completed_at);
        $this->assertSame('Venue confirmed', $done->completion_note);
        $this->assertSame(2, $done->events()->where('type', 'status_changed')->count());
    }

    public function test_assignees_cannot_cancel_and_strangers_cannot_work_on_tasks(): void
    {
        $task = $this->task();

        try {
            $this->tasks->changeStatus($task, TaskStatus::Cancelled, $this->volunteer, $task->version);
            $this->fail('Assignee cancelled a task');
        } catch (AuthorizationDenied) {
        }

        $other = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        $this->expectException(AuthorizationDenied::class);
        $this->tasks->comment($task, $other, 'Hello');
    }

    public function test_evidence_is_hashed_stored_privately_and_downloads_are_audited(): void
    {
        Storage::fake((string) config('campaign.privacy.exports_disk'));
        $task = $this->task();
        $file = UploadedFile::fake()->image('receipt.jpg', 40, 40);
        $expected = hash_file('sha256', $file->getRealPath());

        $evidence = $this->tasks->addEvidence($task, $this->volunteer, $file);

        $this->assertSame($expected, $evidence->sha256);
        Storage::disk($evidence->disk)->assertExists($evidence->path);
        $this->assertStringStartsWith("task-evidence/{$task->uuid}/", $evidence->path);

        $this->signIn($this->lgaCoordinator)->get(route('workspace.tasks.evidence', [$task, $evidence]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(AuditLog::query()->where('action', 'task.evidence_downloaded')->exists());

        $stranger = User::factory()->withRole('lga_coordinator', $this->lga('sapele')->id)->create();
        $this->signIn($stranger)->get(route('workspace.tasks.evidence', [$task, $evidence]))->assertNotFound();

        $this->expectException(ValidationException::class);
        $this->tasks->addEvidence($task, $this->volunteer, UploadedFile::fake()->create('script.html', 1, 'text/html'));
    }

    public function test_overdue_tasks_remind_the_assignee_then_escalate_to_the_area_coordinator(): void
    {
        $task = $this->task(['priority' => 'urgent']);
        $task->forceFill(['due_at' => now()->subHours(3)])->save();

        $this->assertSame(['reminded' => 1, 'escalated' => 1], $this->tasks->processOverdue());
        $this->assertSame(1, $this->volunteer->notifications()->where('type', TaskOverdue::class)->count());
        // Level 1 goes to the coordinator of the task's own ward; the LGA is next.
        $this->assertSame(1, $this->wardCoordinator->notifications()->where('type', TaskEscalated::class)->count());
        $this->assertSame(0, $this->lgaCoordinator->notifications()->count());
        $this->assertSame(1, $task->refresh()->escalation_level);

        // A second run within the day does not remind again, and the next level waits for its grace period.
        $this->assertSame(['reminded' => 0, 'escalated' => 0], $this->tasks->processOverdue());

        $task->forceFill(['due_at' => now()->subHours(5)])->save();
        $this->artisan('tasks:process-overdue')->assertSuccessful();
        $this->assertSame(2, $task->refresh()->escalation_level);
        $this->assertSame(1, $this->lgaCoordinator->notifications()->where('type', TaskEscalated::class)->count());
    }

    public function test_escalation_skips_levels_without_coordinators(): void
    {
        $director = User::factory()->withRole('state_director')->create();
        $task = $this->task();

        $this->assertTrue($this->tasks->escalationRecipients($task)->contains('id', $this->wardCoordinator->id));
        $task->forceFill(['escalation_level' => 1])->save();
        $this->assertTrue($this->tasks->escalationRecipients($task)->contains('id', $this->lgaCoordinator->id));
        $task->forceFill(['escalation_level' => 2])->save();
        // No district coordinator exists, so it goes to the statewide level.
        $this->assertSame([$director->id], $this->tasks->escalationRecipients($task)->pluck('id')->all());
    }

    public function test_task_lists_respect_scope_and_statewide_tasks_stay_statewide(): void
    {
        $director = User::factory()->withRole('state_director')->create();
        $mine = $this->task(['title' => 'Udu ward task']);
        $statewide = $this->tasks->create($director, ['title' => 'Statewide sample task']);
        $sapele = $this->tasks->create($director, ['title' => 'Sapele sample task', 'lga_id' => $this->lga('sapele')->id]);

        Livewire::actingAs($this->lgaCoordinator)->test(TasksIndex::class, ['view' => 'area'])
            ->assertSee('Udu ward task')->assertDontSee('Statewide sample task')->assertDontSee('Sapele sample task');
        Livewire::actingAs($director)->test(TasksIndex::class, ['view' => 'area'])
            ->assertSee('Udu ward task')->assertSee('Statewide sample task')->assertSee('Sapele sample task');
        Livewire::actingAs($this->volunteer)->test(TasksIndex::class)
            ->assertSet('view', 'mine')->assertSee('Udu ward task')->assertDontSee('Statewide sample task');

        $this->signIn($this->lgaCoordinator)->get(route('workspace.tasks.show', $statewide))->assertNotFound();
        $this->signIn($this->lgaCoordinator)->get(route('workspace.tasks.show', $sapele))->assertNotFound();
        $this->signIn($this->volunteer)->get(route('workspace.tasks.show', $mine))->assertOk();
    }

    public function test_the_task_page_handles_conflicts_comments_and_assignment(): void
    {
        $task = $this->task();
        $other = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create(['name' => 'Sample Second Volunteer']);

        $page = Livewire::actingAs($this->volunteer)->test(TaskShow::class, ['task' => $task]);
        $this->tasks->comment($task, $this->wardCoordinator, 'Meanwhile…');
        $this->tasks->changeDue($task->refresh(), now()->addDay(), $this->wardCoordinator);

        $page->call('setStatus', 'in_progress')->assertDispatched('toast', type: 'warning');
        $page->call('setStatus', 'in_progress')->assertDispatched('toast', type: 'success');
        $page->set('comment', 'On my way')->call('addComment')->assertHasNoErrors();
        $this->assertSame(2, $task->comments()->count());

        Livewire::actingAs($this->wardCoordinator)->test(TaskShow::class, ['task' => $task->refresh()])
            ->assertSee('Sample Second Volunteer')
            ->set('assigneeId', $other->id)->call('assign')->assertHasNoErrors();
        $this->assertSame($other->id, $task->refresh()->assigned_to);
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_the_notification_bell_marks_read_and_only_follows_internal_links(): void
    {
        $task = $this->task();
        $notification = $this->volunteer->notifications()->sole();

        Livewire::actingAs($this->volunteer)->test(NotificationBell::class)
            ->assertSee('New task: Sample task')
            ->call('open', $notification->id)
            ->assertRedirect(route('workspace.tasks.show', $task, false));
        $this->assertNotNull($notification->refresh()->read_at);

        $notification->forceFill(['data' => ['title' => 'x', 'url' => 'https://evil.example/']])->save();
        Livewire::actingAs($this->volunteer)->test(NotificationBell::class)
            ->call('open', $notification->id)->assertRedirect(route('workspace.notifications'));

        $someoneElse = User::factory()->withRole('volunteer', $this->ward('udu')->id)->create();
        Livewire::actingAs($someoneElse)->test(NotificationBell::class)->call('open', $notification->id)->assertNotFound();

        $this->signIn($this->volunteer)->get(route('workspace.notifications'))->assertOk();
    }
}
