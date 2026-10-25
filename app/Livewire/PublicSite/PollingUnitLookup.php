<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Public polling-unit search by code or name, or by browsing LGA → ward. Reference data only. */
final class PollingUnitLookup extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(except: null)]
    public ?int $lgaId = null;

    #[Url(except: null)]
    public ?int $wardId = null;

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function render(): View
    {
        $term = trim($this->query);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $results = match (true) {
            mb_strlen($term) >= 3 => PollingUnit::query()->with(['ward:id,name', 'lga:id,name'])
                ->where(fn ($q) => $q->where('code', 'ilike', $like)->orWhere('name', 'ilike', $like))->orderBy('code')->limit(30)->get(),
            $this->wardId !== null => PollingUnit::query()->with(['ward:id,name', 'lga:id,name'])->where('ward_id', $this->wardId)->orderBy('code')->limit(200)->get(),
            default => collect(),
        };

        return view('livewire.public.polling-unit-lookup', [
            'results' => $results,
            'lgas' => Lga::query()->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lgaId ? Ward::query()->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'hasUnits' => PollingUnit::query()->exists(),
        ]);
    }
}
