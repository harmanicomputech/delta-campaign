<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Supporters\StaleRecord;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CMS workflow: draft → in review → approved (or changes requested) → published
 * now or scheduled → withdrawn → archived. The public sees a snapshot taken at
 * publication, so editing never changes a live page until the edit is approved
 * and published again. Every save keeps a version; reviewers cannot approve
 * their own work.
 */
final class ContentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title_en: string, title_pcm?: string|null, summary_en?: string|null, summary_pcm?: string|null, body_en: string, body_pcm?: string|null, slug?: string|null, cover_media_id?: int|null, lga_id?: int|null}  $data
     */
    public function create(User $by, ContentType $type, array $data): ContentItem
    {
        $this->require($by, 'content.create');

        return DB::transaction(function () use ($by, $type, $data): ContentItem {
            $item = new ContentItem(['type' => $type, 'created_by' => $by->id]);
            $this->fill($item, $data);
            $item->save();
            $this->snapshot($item, $by, 'Created');
            WorkflowTransition::record($item, null, ContentStatus::Draft->value, $by);
            $this->audit->record('content.created', $item, null, ['type' => $type->value], $by);

            return $item;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(ContentItem $item, User $by, array $data, int $expectedVersion, ?string $note = null): ContentItem
    {
        $this->require($by, 'content.create');

        if (in_array($item->status, [ContentStatus::InReview, ContentStatus::Archived], true)) {
            throw ValidationException::withMessages(['status' => __('content.locked_'.$item->status->value)]);
        }
        if ($item->version !== $expectedVersion) {
            throw new StaleRecord;
        }

        return DB::transaction(function () use ($item, $by, $data, $note): ContentItem {
            $from = $item->status;
            $this->fill($item, $data);
            // Any change needs review again; a live page keeps serving its published copy meanwhile.
            $item->status = ContentStatus::Draft;
            $item->version++;
            $item->save();
            $this->snapshot($item, $by, $note);
            if ($from !== ContentStatus::Draft) {
                WorkflowTransition::record($item, $from->value, ContentStatus::Draft->value, $by, 'Edited');
            }
            $this->audit->recordChanges('content.updated', $item);

            return $item;
        });
    }

    public function submit(ContentItem $item, User $by): void
    {
        $this->require($by, 'content.create');
        $this->transition($item, [ContentStatus::Draft, ContentStatus::Rejected], ContentStatus::InReview, $by);
    }

    public function approve(ContentItem $item, User $by): void
    {
        $this->require($by, 'content.review');
        if ($item->created_by === $by->id) {
            throw new AuthorizationDenied(__('content.not_own'));
        }

        $this->transition($item, [ContentStatus::InReview], ContentStatus::Approved, $by, extra: ['reviewed_by' => $by->id, 'approved_by' => $by->id, 'review_note' => null]);
    }

    public function reject(ContentItem $item, User $by, string $note): void
    {
        $this->require($by, 'content.review');
        $this->transition($item, [ContentStatus::InReview], ContentStatus::Rejected, $by, $note, ['reviewed_by' => $by->id, 'review_note' => mb_substr($note, 0, 500)]);
    }

    /** Publishes an approved item now, or schedules it when $at is in the future. */
    public function publish(ContentItem $item, User $by, ?\DateTimeInterface $at = null): void
    {
        $this->require($by, 'content.publish');

        if ($at !== null && $at > now()) {
            $this->transition($item, [ContentStatus::Approved], ContentStatus::Scheduled, $by, null, ['publish_at' => $at]);

            return;
        }

        $this->goLive($item, $by);
    }

    /** Publishes scheduled items whose time has come (called every minute). */
    public function publishDue(): int
    {
        $count = 0;
        ContentItem::query()->where('status', ContentStatus::Scheduled)->where('publish_at', '<=', now())->each(function (ContentItem $item) use (&$count): void {
            $this->goLive($item, null);
            $count++;
        });

        return $count;
    }

    public function withdraw(ContentItem $item, User $by, string $reason): void
    {
        $this->require($by, 'content.publish');

        DB::transaction(function () use ($item, $by, $reason): void {
            $from = $item->status;
            $item->forceFill(['status' => ContentStatus::Withdrawn, 'published_snapshot' => null, 'withdrawn_at' => now(), 'publish_at' => null])->save();
            WorkflowTransition::record($item, $from->value, ContentStatus::Withdrawn->value, $by, $reason);
            $this->audit->record('content.withdrawn', $item, null, ['reason' => $reason], $by);
        });
    }

    public function archive(ContentItem $item, User $by): void
    {
        $this->require($by, 'content.publish');

        if ($item->isLive()) {
            throw ValidationException::withMessages(['status' => __('content.withdraw_first')]);
        }

        $this->transition($item, [ContentStatus::Draft, ContentStatus::Rejected, ContentStatus::Withdrawn, ContentStatus::Approved], ContentStatus::Archived, $by);
    }

    /** Copies an earlier version into the working copy (as a draft needing review). */
    public function restore(ContentItem $item, ContentVersion $version, User $by): ContentItem
    {
        abort_unless($version->content_item_id === $item->id, 404);

        return $this->update($item, $by, $version->snapshot, $item->version, __('content.restored_from', ['version' => $version->version]));
    }

    private function goLive(ContentItem $item, ?User $by): void
    {
        if (! in_array($item->status, [ContentStatus::Approved, ContentStatus::Scheduled], true)) {
            throw ValidationException::withMessages(['status' => __('content.approve_first')]);
        }

        DB::transaction(function () use ($item, $by): void {
            $from = $item->status;
            $item->forceFill([
                'status' => ContentStatus::Published,
                'published_snapshot' => $item->only(ContentItem::VERSIONED) + ['version' => $item->version],
                'published_at' => $item->published_at && $item->isLive() ? $item->published_at : now(),
                'withdrawn_at' => null,
                'publish_at' => null,
            ])->save();
            WorkflowTransition::record($item, $from->value, ContentStatus::Published->value, $by);
            $this->audit->as($by ?? 'content-scheduler', fn () => $this->audit->record('content.published', $item, null, ['version' => $item->version]));
        });
    }

    /**
     * @param  list<ContentStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function transition(ContentItem $item, array $from, ContentStatus $to, User $by, ?string $note = null, array $extra = []): void
    {
        if (! in_array($item->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('content.cannot_move', ['from' => $item->status->label(), 'to' => $to->label()])]);
        }

        DB::transaction(function () use ($item, $to, $by, $note, $extra): void {
            $previous = $item->status;
            $item->forceFill(['status' => $to] + $extra)->save();
            WorkflowTransition::record($item, $previous->value, $to->value, $by, $note);
            $this->audit->record('content.'.$to->value, $item, null, $note ? ['note' => $note] : [], $by);
        });
    }

    /** @param array<string, mixed> $data */
    private function fill(ContentItem $item, array $data): void
    {
        foreach (['title_en', 'title_pcm', 'summary_en', 'summary_pcm', 'body_en', 'body_pcm', 'cover_media_id', 'lga_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $item->{$field} = is_string($data[$field]) ? (trim($data[$field]) === '' ? null : trim($data[$field])) : $data[$field];
            }
        }

        $item->body_en ??= '';
        $slug = Str::slug((string) ($data['slug'] ?? '') ?: $item->title_en);
        $slug = $slug !== '' ? mb_substr($slug, 0, 110) : 'item';

        if ($item->slug !== $slug) {
            $candidate = $slug;
            for ($i = 2; ContentItem::query()->where('type', $item->type)->where('slug', $candidate)->whereKeyNot($item->id ?? 0)->exists(); $i++) {
                $candidate = $slug.'-'.$i;
            }
            $item->slug = $candidate;
        }
    }

    private function snapshot(ContentItem $item, User $by, ?string $note): void
    {
        ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version' => $item->version,
            'snapshot' => $item->only(ContentItem::VERSIONED),
            'user_id' => $by->id,
            'note' => $note !== null ? mb_substr($note, 0, 300) : null,
            'created_at' => now(),
        ]);
    }

    private function require(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
