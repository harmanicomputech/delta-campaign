<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\SendTransactionalSms;
use App\Models\CampaignEvent;
use App\Models\EventRegistration;
use App\Models\Lga;
use App\Models\Supporter;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Users\AuthorizationDenied;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Events: create and publish (events.manage, in the organiser's area), public
 * registration with capacity limits and an opaque QR ticket, check-in by
 * scanning the ticket, and SMS notices for confirmation and cancellation.
 */
final class EventService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function save(User $by, array $data, ?CampaignEvent $event = null): CampaignEvent
    {
        $this->require($by, 'events.manage');

        $ward = empty($data['ward_id']) ? null : Ward::query()->findOrFail($data['ward_id']);
        $lga = $ward ? Lga::query()->find($ward->lga_id) : (empty($data['lga_id']) ? null : Lga::query()->findOrFail($data['lga_id']));
        $scope = $by->geoScope('events.manage');
        $allowed = $ward ? $scope->contains($ward->senatorial_district_id, $ward->lga_id, $ward->id) : ($lga ? $scope->contains($lga->senatorial_district_id, $lga->id) : $scope->statewide);

        if (! $allowed) {
            throw ValidationException::withMessages(['lga_id' => __('events.outside_area')]);
        }
        if ($event !== null && $event->status === EventStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => __('events.cancelled_locked')]);
        }

        $event ??= new CampaignEvent(['created_by' => $by->id]);
        $event->fill([
            'title_en' => mb_substr(trim((string) $data['title_en']), 0, 200),
            'title_pcm' => filled($data['title_pcm'] ?? null) ? mb_substr((string) $data['title_pcm'], 0, 200) : null,
            'description_en' => trim((string) $data['description_en']),
            'description_pcm' => filled($data['description_pcm'] ?? null) ? (string) $data['description_pcm'] : null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'venue' => mb_substr(trim((string) $data['venue']), 0, 200),
            'lga_id' => $lga?->id,
            'ward_id' => $ward?->id,
            'senatorial_district_id' => $lga?->senatorial_district_id,
            'capacity' => filled($data['capacity'] ?? null) ? max(1, (int) $data['capacity']) : null,
            'registration_open' => (bool) ($data['registration_open'] ?? true),
            'cover_media_id' => $data['cover_media_id'] ?? $event->cover_media_id,
        ]);
        if (! $event->exists) {
            $slug = Str::slug($event->title_en) ?: 'event';
            $event->slug = $slug.'-'.strtolower(Str::random(5));
        }
        $event->save();
        $this->audit->record($event->wasRecentlyCreated ? 'event.created' : 'event.updated', $event, null, [], $by);

        return $event;
    }

    public function publish(CampaignEvent $event, User $by): void
    {
        $this->assertCanManage($event, $by);
        if ($event->status !== EventStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('events.not_draft')]);
        }

        $event->forceFill(['status' => EventStatus::Published, 'published_at' => now()])->save();
        $this->audit->record('event.published', $event, null, [], $by);
    }

    public function cancel(CampaignEvent $event, User $by, string $reason): int
    {
        $this->assertCanManage($event, $by);
        if ($event->status === EventStatus::Cancelled) {
            return 0;
        }

        $notified = 0;
        DB::transaction(function () use ($event, $by, $reason, &$notified): void {
            $event->forceFill(['status' => EventStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => mb_substr($reason, 0, 300), 'registration_open' => false])->save();
            $this->audit->record('event.cancelled', $event, null, ['reason' => $reason], $by);

            // Registrants asked to hear about this event, so a cancellation notice is transactional.
            $event->registrations()->where('status', RegistrationStatus::Registered)->each(function (EventRegistration $r) use ($event, &$notified): void {
                SendTransactionalSms::dispatch((string) $r->phone_encrypted, __('events.sms_cancelled', ['title' => mb_strimwidth($event->title_en, 0, 60, '…'), 'date' => $event->starts_at->timezone(config('campaign.display_timezone'))->format('j M')]), 'event.cancelled');
                $notified++;
            });
        });

        return $notified;
    }

    /**
     * Registers a person. The same number registering again gets their existing ticket.
     *
     * @return array{registration: EventRegistration, created: bool}
     */
    public function register(CampaignEvent $event, string $name, string $phone, string $source, ?User $by = null): array
    {
        $e164 = NigerianPhoneNumber::normalise($phone) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);
        $hash = PhoneHasher::hash($e164);

        return DB::transaction(function () use ($event, $name, $e164, $hash, $source, $by): array {
            $locked = CampaignEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($existing = EventRegistration::query()->where('event_id', $locked->id)->where('phone_hash', $hash)->first()) {
                if ($existing->status === RegistrationStatus::Cancelled) {
                    $existing->forceFill(['status' => RegistrationStatus::Registered])->save();
                }

                return ['registration' => $existing, 'created' => false];
            }

            // At the door the event may already have started; capacity still applies.
            $open = $source === 'walk_in'
                ? $locked->status === EventStatus::Published && $locked->spacesLeft() !== 0
                : $locked->acceptsRegistrations();

            if (! $open) {
                throw ValidationException::withMessages(['event' => $locked->spacesLeft() === 0 ? __('events.full') : __('events.closed')]);
            }

            $registration = new EventRegistration([
                'event_id' => $locked->id,
                'supporter_id' => Supporter::query()->where('phone_hash', $hash)->value('id'),
                'full_name' => mb_substr(trim((string) preg_replace('/\s+/', ' ', $name)), 0, 150),
                'phone_hash' => $hash,
                'phone_last4' => substr($e164, -4),
                'source' => $source,
            ]);
            $registration->phone_encrypted = $e164;
            $registration->save();

            SendTransactionalSms::dispatch($e164, __('events.sms_registered', [
                'title' => mb_strimwidth($locked->title_en, 0, 50, '…'),
                'date' => $locked->starts_at->timezone(config('campaign.display_timezone'))->format('j M, H:i'),
                'url' => route('public.events.ticket', $registration->ticket_token),
            ]), 'event.registered');

            $this->audit->as($by ?? 'public', fn () => $this->audit->record('event.registered', $locked, null, ['source' => $source]));

            return ['registration' => $registration, 'created' => true];
        });
    }

    /** Marks a ticket as attended. Returns the registration (already attended ones are returned unchanged). */
    public function checkIn(string $token, User $by): EventRegistration
    {
        $registration = EventRegistration::query()->with('event')->where('ticket_token', $token)->first()
            ?? throw ValidationException::withMessages(['token' => __('events.ticket_unknown')]);

        $this->assertCanManage($registration->event, $by);

        if ($registration->status === RegistrationStatus::Cancelled) {
            throw ValidationException::withMessages(['token' => __('events.ticket_cancelled')]);
        }

        if ($registration->status !== RegistrationStatus::Attended) {
            $registration->forceFill(['status' => RegistrationStatus::Attended, 'checked_in_at' => now(), 'checked_in_by' => $by->id])->save();
        }

        return $registration;
    }

    public function canManage(CampaignEvent $event, User $user): bool
    {
        if (! $user->hasPermission('events.manage')) {
            return false;
        }
        $scope = $user->geoScope('events.manage');

        return $event->lga_id === null ? $scope->statewide : $event->isWithin($scope);
    }

    private function assertCanManage(CampaignEvent $event, User $by): void
    {
        if (! $this->canManage($event, $by)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }

    private function require(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new AuthorizationDenied(__('users.not_allowed'));
        }
    }
}
