<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Events;

use App\Models\CampaignEvent;
use App\Models\Lga;
use App\Models\Media;
use App\Models\User;
use App\Models\Ward;
use App\Services\Content\MediaService;
use App\Services\Events\EventService;
use App\Services\Users\AuthorizationDenied;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class EventEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $eventId = null;

    public string $titleEn = '';

    public string $titlePcm = '';

    public string $descriptionEn = '';

    public string $descriptionPcm = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $venue = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public string $capacity = '';

    public bool $registrationOpen = true;

    public ?int $coverId = null;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public function mount(EventService $events, ?CampaignEvent $event = null): void
    {
        $this->authorize('events.manage');
        $tz = (string) config('campaign.display_timezone');

        if ($event?->exists) {
            abort_unless($events->canManage($event, $this->actor()), 403);
            $this->eventId = $event->id;
            $this->fill(['titleEn' => $event->title_en, 'titlePcm' => (string) $event->title_pcm, 'descriptionEn' => $event->description_en, 'descriptionPcm' => (string) $event->description_pcm,
                'startsAt' => $event->starts_at->timezone($tz)->format('Y-m-d\TH:i'), 'endsAt' => $event->ends_at?->timezone($tz)->format('Y-m-d\TH:i') ?? '',
                'venue' => $event->venue, 'lgaId' => $event->lga_id, 'wardId' => $event->ward_id, 'capacity' => (string) ($event->capacity ?? ''),
                'registrationOpen' => $event->registration_open, 'coverId' => $event->cover_media_id]);
        } elseif (! ($scope = $this->actor()->geoScope('events.manage'))->statewide) {
            $this->lgaId = $scope->lgaIds[0] ?? Ward::query()->whereIn('id', $scope->wardIds)->value('lga_id');
            $this->wardId = $scope->lgaIds === [] && $scope->districtIds === [] ? ($scope->wardIds[0] ?? null) : null;
        }
    }

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function updatedImage(MediaService $media): void
    {
        $this->validate(['image' => ['image', 'max:8192', 'mimes:jpg,jpeg,png,webp']]);
        $this->coverId = $media->storeImage($this->image, $this->actor(), $this->titleEn)->id;
        $this->reset('image');
    }

    public function save(EventService $events): void
    {
        $this->validate([
            'titleEn' => ['required', 'string', 'min:3', 'max:200'],
            'titlePcm' => ['nullable', 'string', 'max:200'],
            'descriptionEn' => ['required', 'string', 'max:10000'],
            'descriptionPcm' => ['nullable', 'string', 'max:10000'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
            'venue' => ['required', 'string', 'max:200'],
            'lgaId' => ['nullable', 'integer'],
            'wardId' => ['nullable', 'integer'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ], [], ['titleEn' => __('content.title_en'), 'descriptionEn' => __('events.description'), 'startsAt' => __('events.starts_at')]);

        $tz = (string) config('campaign.display_timezone');

        try {
            $event = $events->save($this->actor(), [
                'title_en' => $this->titleEn, 'title_pcm' => $this->titlePcm, 'description_en' => $this->descriptionEn, 'description_pcm' => $this->descriptionPcm,
                'starts_at' => CarbonImmutable::parse($this->startsAt, $tz)->utc(), 'ends_at' => $this->endsAt ? CarbonImmutable::parse($this->endsAt, $tz)->utc() : null,
                'venue' => $this->venue, 'lga_id' => $this->lgaId, 'ward_id' => $this->wardId, 'capacity' => $this->capacity,
                'registration_open' => $this->registrationOpen, 'cover_media_id' => $this->coverId,
            ], $this->eventId ? CampaignEvent::query()->findOrFail($this->eventId) : null);
        } catch (AuthorizationDenied $e) {
            $this->addError('lgaId', $e->getMessage());

            return;
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? $e->errors());

            return;
        }

        session()->flash('success', __('events.saved'));
        $this->redirectRoute('workspace.events.show', $event);
    }

    public function render(): View
    {
        $scope = $this->actor()->geoScope('events.manage');

        return view('livewire.workspace.events.event-editor', [
            'lgas' => Lga::query()->when(! $scope->statewide, fn ($q) => $q->where(fn ($q) => $q->whereIn('id', $scope->lgaIds)->orWhereIn('senatorial_district_id', $scope->districtIds)
                ->orWhereIn('id', Ward::query()->whereIn('id', $scope->wardIds)->select('lga_id'))))->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lgaId ? Ward::query()->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'statewide' => $scope->statewide,
            'cover' => $this->coverId ? Media::query()->find($this->coverId) : null,
        ])->title($this->eventId ? __('events.edit') : __('events.new'))
            ->layoutData(['breadcrumbs' => [__('events.title') => route('workspace.events.index'), ($this->eventId ? __('events.edit') : __('events.new')) => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
