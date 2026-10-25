<?php

declare(strict_types=1);

namespace App\Services\RapidResponse;

use App\Enums\CaseStatus;
use App\Enums\ContentType;
use App\Enums\TaskPriority;
use App\Enums\Verdict;
use App\Models\RapidResponseCase;
use App\Models\RapidResponseNote;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Content\ContentService;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rapid-response cases for claims and incidents: new → investigating →
 * (legal review) → awaiting approval → approved, or closed / no response.
 * Approval never publishes anything: it creates a draft fact-check that must
 * pass the normal content review before it can go live.
 */
final class RapidResponseService
{
    public const array EVIDENCE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain'];

    public function __construct(private readonly AuditLogger $audit, private readonly ContentService $content) {}

    /** @param array{title: string, claim: string, source_urls?: list<string>, priority?: string, deadline_at?: \DateTimeInterface|null, requires_legal?: bool} $data */
    public function open(User $by, array $data): RapidResponseCase
    {
        $this->require($by, 'rapid-response.manage');

        $urls = array_values(array_filter(array_map('trim', $data['source_urls'] ?? []), fn (string $u) => filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u)));

        return DB::transaction(function () use ($by, $data, $urls): RapidResponseCase {
            $case = RapidResponseCase::query()->create([
                'title' => mb_substr($data['title'], 0, 200),
                'claim' => $data['claim'],
                'source_urls' => array_slice($urls, 0, 10),
                'priority' => TaskPriority::tryFrom($data['priority'] ?? 'normal') ?? TaskPriority::Normal,
                'deadline_at' => $data['deadline_at'] ?? null,
                'requires_legal' => (bool) ($data['requires_legal'] ?? false),
                'created_by' => $by->id,
                'status' => CaseStatus::New,
            ]);
            WorkflowTransition::record($case, null, CaseStatus::New->value, $by);
            $this->audit->record('rapid_response.opened', $case, null, ['priority' => $case->priority->value], $by);

            return $case;
        });
    }

    public function assign(RapidResponseCase $case, User $assignee, User $by): void
    {
        $this->require($by, 'rapid-response.manage');
        if (! $assignee->isActive() || ! $assignee->hasPermission('rapid-response.manage')) {
            throw ValidationException::withMessages(['assignee' => __('rapid.assignee_invalid')]);
        }

        $case->forceFill(['assigned_to' => $assignee->id, 'version' => $case->version + 1])->save();
        $this->note($case, $by, 'status', __('rapid.assigned_to', ['name' => $assignee->name]));
        $this->audit->record('rapid_response.assigned', $case, null, ['to' => $assignee->id], $by);
    }

    public function addNote(RapidResponseCase $case, User $by, string $body): void
    {
        $this->require($by, 'rapid-response.manage');
        $this->note($case, $by, 'note', mb_substr($body, 0, 4000));
    }

    public function addEvidence(RapidResponseCase $case, User $by, UploadedFile $file, ?string $description = null): RapidResponseNote
    {
        $this->require($by, 'rapid-response.manage');
        if (! in_array($file->getMimeType(), self::EVIDENCE_MIMES, true)) {
            throw ValidationException::withMessages(['evidence' => __('rapid.evidence_type')]);
        }

        $disk = (string) config('campaign.privacy.exports_disk');
        $path = $file->storeAs("rapid-response/{$case->uuid}", (string) Str::uuid().'.'.($file->guessExtension() ?: 'bin'), $disk);
        $hash = (string) hash_file('sha256', $file->getRealPath());

        $note = $case->notes()->create([
            'user_id' => $by->id, 'kind' => 'evidence', 'body' => $description !== null ? mb_substr($description, 0, 1000) : null,
            'disk' => $disk, 'path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime' => (string) $file->getMimeType(), 'sha256' => $hash, 'created_at' => now(),
        ]);
        $this->audit->record('rapid_response.evidence_added', $case, null, ['sha256' => $hash], $by);

        return $note;
    }

    public function recordFinding(RapidResponseCase $case, User $by, Verdict $verdict, string $finding): void
    {
        $this->require($by, 'rapid-response.manage');
        $case->forceFill(['verdict' => $verdict, 'finding' => $finding, 'version' => $case->version + 1])->save();
        $this->note($case, $by, 'status', __('rapid.finding_recorded', ['verdict' => $verdict->label()]));
    }

    public function startInvestigation(RapidResponseCase $case, User $by): void
    {
        $this->require($by, 'rapid-response.manage');
        $this->move($case, [CaseStatus::New], CaseStatus::Investigating, $by);
    }

    /** Sends to legal review when the case needs it, otherwise straight to approval. */
    public function requestApproval(RapidResponseCase $case, User $by): void
    {
        $this->require($by, 'rapid-response.manage');
        if ($case->verdict === null || blank($case->finding)) {
            throw ValidationException::withMessages(['finding' => __('rapid.finding_required')]);
        }

        $this->move($case, [CaseStatus::Investigating], $case->requires_legal ? CaseStatus::LegalReview : CaseStatus::AwaitingApproval, $by);
    }

    public function clearLegal(RapidResponseCase $case, User $by, string $note): void
    {
        $this->require($by, 'rapid-response.approve');
        $this->move($case, [CaseStatus::LegalReview], CaseStatus::AwaitingApproval, $by, $note, ['legal_cleared_by' => $by->id, 'legal_cleared_at' => now()]);
    }

    /** Approves the response and creates a draft fact-check for the normal content workflow. */
    public function approve(RapidResponseCase $case, User $by): void
    {
        $this->require($by, 'rapid-response.approve');
        if ($case->created_by === $by->id) {
            throw new AuthorizationDenied(__('rapid.not_own'));
        }

        DB::transaction(function () use ($case, $by): void {
            $this->move($case, [CaseStatus::AwaitingApproval], CaseStatus::Approved, $by, null, ['approved_by' => $by->id, 'approved_at' => now()]);

            if ($case->content_item_id === null && $by->hasPermission('content.create')) {
                $item = $this->content->create($by, ContentType::FactCheck, [
                    'title_en' => $case->title,
                    'summary_en' => mb_substr(__('rapid.verdict_line', ['verdict' => $case->verdict?->label()]), 0, 500),
                    'body_en' => '**'.__('rapid.claim').'** '.$case->claim."\n\n**".__('rapid.verdict').':** '.$case->verdict?->label()."\n\n".$case->finding,
                ]);
                $case->forceFill(['content_item_id' => $item->id])->save();
            }
        });
    }

    public function close(RapidResponseCase $case, User $by, string $reason, bool $noResponse = false): void
    {
        $this->require($by, 'rapid-response.manage');
        $this->move($case, [CaseStatus::New, CaseStatus::Investigating, CaseStatus::LegalReview, CaseStatus::AwaitingApproval, CaseStatus::Approved],
            $noResponse ? CaseStatus::Rejected : CaseStatus::Closed, $by, $reason);
    }

    /**
     * @param  list<CaseStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function move(RapidResponseCase $case, array $from, CaseStatus $to, User $by, ?string $note = null, array $extra = []): void
    {
        if (! in_array($case->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('content.cannot_move', ['from' => $case->status->label(), 'to' => $to->label()])]);
        }

        $previous = $case->status;
        $case->forceFill(['status' => $to, 'version' => $case->version + 1] + $extra)->save();
        WorkflowTransition::record($case, $previous->value, $to->value, $by, $note);
        $this->note($case, $by, 'status', $to->label().($note ? ': '.$note : ''));
        $this->audit->record('rapid_response.'.$to->value, $case, null, $note ? ['note' => $note] : [], $by);
    }

    private function note(RapidResponseCase $case, User $by, string $kind, string $body): void
    {
        $case->notes()->create(['user_id' => $by->id, 'kind' => $kind, 'body' => $body, 'created_at' => now()]);
    }

    private function require(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
