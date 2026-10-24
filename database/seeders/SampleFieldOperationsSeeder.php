<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContactOutcome;
use App\Enums\ScopeType;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\Ward;
use App\Services\Canvassing\CanvassingService;
use App\Services\Tasks\TaskService;
use App\Services\Volunteers\TrainingService;
use App\Services\Volunteers\VolunteerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SAMPLE volunteers, coordinators, training, walk lists and tasks for
 * development. People are named "Sample …", modules have "sample-" slugs and
 * task titles start with "SAMPLE:". Never runs in production.
 */
class SampleFieldOperationsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample field data must never be seeded in production.');
        }

        $ward = Ward::query()->where('is_sample', true)->orderBy('id')->firstOrFail();
        $otherWard = Ward::query()->where('is_sample', true)->where('lga_id', $ward->lga_id)->whereKeyNot($ward->id)->orderBy('id')->first() ?? $ward;

        $lgaCoordinator = $this->person('Sample LGA Coordinator', '08170000001', 'lga_coordinator', ScopeType::Lga, $ward->lga_id);
        $wardCoordinator = $this->person('Sample Ward Coordinator', '08170000002', 'ward_coordinator', ScopeType::Ward, $ward->id);
        $trainer = $this->trainingManager();

        // Volunteers: 12 applications, half approved.
        $volunteers = app(VolunteerService::class);
        $skills = [['canvassing', 'phone'], ['events', 'transport'], ['media'], ['canvassing', 'language'], ['data'], ['agent', 'canvassing']];
        $approved = [];
        foreach (range(1, 12) as $i) {
            $volunteer = $volunteers->apply([
                'full_name' => sprintf('Sample Volunteer %02d', $i),
                'phone' => sprintf('0816%07d', $i),
                'ward_id' => $i % 4 === 0 ? $otherWard->id : $ward->id,
                'skills' => $skills[$i % count($skills)],
                'availability' => $i % 2 ? ['weekend', 'election_day'] : ['weekday_evening'],
            ], 'staff', $wardCoordinator, sample: true);

            if ($i <= 6 && $volunteer->status->value === 'applied') {
                $user = $volunteers->approve($volunteer, $lgaCoordinator)->user;
                if ($volunteer->ward_id === $ward->id) {
                    $approved[] = $user;
                }
            }
        }

        $this->training($trainer);

        // A walk list with street stops and supporters, assigned to the first approved volunteer.
        $canvassing = app(CanvassingService::class);
        $list = $canvassing->createWalkList($wardCoordinator, 'SAMPLE: Market road round', $ward, ['[SAMPLE] Market road, north end', '[SAMPLE] Market road, south end', '[SAMPLE] Junction by the school'], includeSupporters: true, dueOn: now()->addDays(5));
        if ($first = $approved[0] ?? null) {
            $canvassing->assign($list, $first, $wardCoordinator);
            foreach ($list->entries()->orderBy('position')->limit(4)->get() as $n => $entry) {
                $canvassing->recordContact($entry, $first, [ContactOutcome::Reached, ContactOutcome::NotHome, ContactOutcome::FollowUpRequested, ContactOutcome::Reached][$n], occurredAt: now()->subHours(4 - $n));
            }
        }

        // Tasks across priorities, one already overdue.
        $tasks = app(TaskService::class);
        $definitions = [
            ['SAMPLE: Confirm meeting venue for ward volunteers', 'high', now()->addDay()],
            ['SAMPLE: Distribute training reminders', 'normal', now()->addDays(3)],
            ['SAMPLE: Report damaged notice boards', 'low', null],
            ['SAMPLE: Collect walk-list returns', 'urgent', now()->subHours(6)],
        ];
        foreach ($definitions as $n => [$title, $priority, $due]) {
            $task = $tasks->create($wardCoordinator, ['title' => $title, 'description' => 'Illustrative sample task for development.', 'priority' => $priority, 'ward_id' => $ward->id]);
            $task->forceFill(['due_at' => $due])->save();
            if ($assignee = $approved[$n % max(1, count($approved))] ?? null) {
                $tasks->assign($task, $assignee, $wardCoordinator);
            }
        }
    }

    private function person(string $name, string $phone, string $role, ScopeType $type, ?int $scopeId): User
    {
        $user = User::findByPhone($phone) ?? tap(new User(['name' => $name]), fn (User $u) => $u->setPhone($phone)->save());

        $roleId = Role::query()->where('slug', $role)->value('id');
        if (! $user->activeAssignments()->where('role_id', $roleId)->exists()) {
            RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roleId, 'scope_type' => $type, 'scope_id' => $scopeId]);
            $user->flushAuthorizationCache();
        }

        return $user;
    }

    private function training(User $by): void
    {
        $service = app(TrainingService::class);

        $modules = [
            [
                'title' => 'SAMPLE: Respectful door-to-door conduct',
                'summary' => 'Illustrative module showing the format. Replace with the campaign’s approved training.',
                'body' => "> **SAMPLE content.** Replace with material approved by the campaign.\n\n## Before you knock\n\n- Wear your volunteer identification.\n- Check your walk list and briefing notes.\n\n## At the door\n\n- Introduce yourself and the campaign.\n- Respect a \"no\" straight away, and record **Do not contact** if asked.\n- Never offer money or gifts in exchange for support.\n\n## What to record\n\nRecord whether you reached the person. Never record how someone intends to vote, their religion or their ethnicity.",
                'audience' => 'volunteer',
                'questions' => [
                    ['Someone says they do not want to be contacted again. What do you record?', ['Refused', 'Do not contact', 'Not home'], 1],
                    ['Which of these should you never record?', ['Whether you reached them', 'Their voting intention', 'That they asked for a follow-up'], 1],
                    ['Can you offer a gift in exchange for support?', ['Yes, if small', 'No, never'], 1],
                ],
            ],
            [
                'title' => 'SAMPLE: Looking after personal data',
                'summary' => 'Illustrative module on handling supporter information safely.',
                'body' => "> **SAMPLE content.** Replace with material approved by the Data Protection Officer.\n\n## The basics\n\n- Only collect what the person agrees to share.\n- Never photograph or copy supporter lists.\n- Report a lost phone to your coordinator immediately.",
                'audience' => 'all',
                'questions' => [
                    ['Your phone with the app is lost. What do you do first?', ['Wait a few days', 'Tell your coordinator immediately', 'Nothing'], 1],
                    ['May you copy a supporter list to your personal notes?', ['Yes', 'No'], 1],
                ],
            ],
        ];

        foreach ($modules as $i => $definition) {
            $slug = 'sample-'.Str::slug(str_replace('SAMPLE: ', '', $definition['title']));
            if (TrainingModule::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $module = TrainingModule::query()->create([
                'slug' => $slug, 'title' => $definition['title'], 'summary' => $definition['summary'], 'body' => $definition['body'],
                'audience' => $definition['audience'], 'language' => 'en', 'pass_mark' => 70, 'sort' => $i + 1, 'created_by' => $by->id,
            ]);
            foreach ($definition['questions'] as [$prompt, $options, $correct]) {
                $service->addQuestion($by, $module, $prompt, $options, $correct);
            }
            $module->forceFill(['published_at' => now()])->save();
        }
    }

    private function trainingManager(): User
    {
        return $this->person('Sample State Director', '08170000003', 'state_director', ScopeType::State, null);
    }
}
