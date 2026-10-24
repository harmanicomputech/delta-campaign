<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedField;
use App\Enums\DsrStatus;
use App\Enums\DsrType;
use App\Enums\RequestChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property DsrType $type
 * @property DsrStatus $status
 * @property RequestChannel $channel
 * @property string $phone_hash
 * @property string $phone_encrypted
 * @property int|null $supporter_id
 * @property string|null $details
 * @property Carbon|null $identity_verified_at
 * @property string|null $verification_method
 * @property Carbon $due_at
 * @property int|null $assigned_to
 * @property string|null $resolution
 * @property string|null $export_path
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 */
class DataSubjectRequest extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['phone_encrypted'];

    protected function casts(): array
    {
        return [
            'type' => DsrType::class,
            'status' => DsrStatus::class,
            'channel' => RequestChannel::class,
            'phone_encrypted' => EncryptedField::class,
            'identity_verified_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [DsrStatus::Completed, DsrStatus::Rejected], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at->isPast();
    }

    public function maskedPhone(): string
    {
        return '+234 ••• ••• '.substr((string) $this->phone_encrypted, -4);
    }

    /** @return BelongsTo<Supporter, $this> */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(Supporter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return MorphMany<WorkflowTransition, $this> */
    public function transitions(): MorphMany
    {
        return $this->morphMany(WorkflowTransition::class, 'subject')->latest('created_at');
    }
}
