<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVerification;
use App\Models\Lga;
use App\Models\Project;
use App\Models\User;
use App\Models\Ward;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public project directory. Records are drafted (projects.manage) with a
 * mandatory source note, then approved and published by a different person
 * (projects.approve). Nothing is published without that approval.
 */
final class ProjectService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function save(User $by, array $data, ?Project $project = null): Project
    {
        $this->require($by, 'projects.manage');

        if ($project !== null && ! in_array($project->status, [ProjectStatus::Draft, ProjectStatus::Rejected, ProjectStatus::Withdrawn, ProjectStatus::Published], true)) {
            throw ValidationException::withMessages(['status' => __('projects.locked')]);
        }
        if (! in_array($data['category'] ?? null, Project::CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => __('validation.in', ['attribute' => 'category'])]);
        }

        $ward = empty($data['ward_id']) ? null : Ward::query()->findOrFail($data['ward_id']);
        $lga = $ward ? Lga::query()->find($ward->lga_id) : (empty($data['lga_id']) ? null : Lga::query()->findOrFail($data['lga_id']));

        return DB::transaction(function () use ($by, $data, $project, $ward, $lga): Project {
            $wasPublished = $project?->status === ProjectStatus::Published;
            $project ??= new Project(['created_by' => $by->id]);
            $project->fill([
                'title' => mb_substr(trim((string) $data['title']), 0, 200),
                'description' => trim((string) $data['description']),
                'category' => $data['category'],
                'lga_id' => $lga?->id,
                'ward_id' => $ward?->id,
                'senatorial_district_id' => $lga?->senatorial_district_id,
                'location' => filled($data['location'] ?? null) ? mb_substr((string) $data['location'], 0, 200) : null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'source_note' => mb_substr(trim((string) $data['source_note']), 0, 500),
                'verification' => ProjectVerification::tryFrom((string) ($data['verification'] ?? '')) ?? ProjectVerification::Unverified,
                'cover_media_id' => $data['cover_media_id'] ?? $project->cover_media_id,
            ]);
            if (! $project->exists || $project->isDirty('title')) {
                $slug = Str::slug($project->title) ?: 'project';
                $candidate = $slug;
                for ($i = 2; Project::query()->where('slug', $candidate)->whereKeyNot($project->id ?? 0)->exists(); $i++) {
                    $candidate = $slug.'-'.$i;
                }
                $project->slug = $candidate;
            }
            // Edits to a published record take it off the site until approved again.
            $project->status = ProjectStatus::Draft;
            $project->version = $project->exists ? $project->version + 1 : 1;
            $project->save();

            if ($wasPublished) {
                WorkflowTransition::record($project, ProjectStatus::Published->value, ProjectStatus::Draft->value, $by, 'Edited; republication needs approval');
            }
            $this->audit->record($project->wasRecentlyCreated ? 'project.created' : 'project.updated', $project, null, [], $by);

            return $project;
        });
    }

    public function submit(Project $project, User $by): void
    {
        $this->require($by, 'projects.manage');
        $this->move($project, [ProjectStatus::Draft, ProjectStatus::Rejected], ProjectStatus::InReview, $by);
    }

    public function publish(Project $project, User $by): void
    {
        $this->require($by, 'projects.approve');
        if ($project->created_by === $by->id) {
            throw new AuthorizationDenied(__('projects.not_own'));
        }

        $this->move($project, [ProjectStatus::InReview], ProjectStatus::Published, $by, null, ['approved_by' => $by->id, 'published_at' => now(), 'review_note' => null]);
    }

    public function reject(Project $project, User $by, string $note): void
    {
        $this->require($by, 'projects.approve');
        $this->move($project, [ProjectStatus::InReview], ProjectStatus::Rejected, $by, $note, ['review_note' => mb_substr($note, 0, 500)]);
    }

    public function withdraw(Project $project, User $by, string $reason): void
    {
        $this->require($by, 'projects.approve');
        $this->move($project, [ProjectStatus::Published], ProjectStatus::Withdrawn, $by, $reason);
    }

    /**
     * @param  list<ProjectStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function move(Project $project, array $from, ProjectStatus $to, User $by, ?string $note = null, array $extra = []): void
    {
        if (! in_array($project->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('content.cannot_move', ['from' => $project->status->label(), 'to' => $to->label()])]);
        }

        DB::transaction(function () use ($project, $to, $by, $note, $extra): void {
            $previous = $project->status;
            $project->forceFill(['status' => $to] + $extra)->save();
            WorkflowTransition::record($project, $previous->value, $to->value, $by, $note);
            $this->audit->record('project.'.$to->value, $project, null, $note ? ['note' => $note] : [], $by);
        });
    }

    private function require(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
