<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Election;

use App\Models\Election;
use App\Models\User;
use App\Services\Election\ElectionSetup;
use App\Services\Users\AuthorizationDenied;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Election configuration: date, parties (copied from the official ballot), opening checklist and turnout times. */
final class ElectionSetupPage extends Component
{
    #[Locked]
    public ?int $electionId = null;

    public string $name = '';

    public string $date = '';

    /** One party per line: CODE Name */
    public string $parties = '';

    /** One checklist item per line */
    public string $checklist = '';

    public string $turnoutTimes = '10:00, 12:00, 14:00';

    public function mount(): void
    {
        $this->authorize('election.setup');
        if ($election = Election::current()?->load('parties')) {
            $this->load($election);
        } else {
            $this->checklist = implode("\n", (array) __('election.default_checklist'));
        }
    }

    public function edit(int $id): void
    {
        $this->load(Election::query()->with('parties')->findOrFail($id));
    }

    public function create(): void
    {
        $this->reset('electionId', 'name', 'date', 'parties');
        $this->checklist = implode("\n", (array) __('election.default_checklist'));
    }

    private function load(Election $election): void
    {
        $this->electionId = $election->id;
        $this->name = $election->name;
        $this->date = $election->election_date->toDateString();
        $this->parties = $election->parties->map(fn ($p) => $p->code.' '.$p->name)->implode("\n");
        $this->checklist = collect($election->checklist)->pluck('label')->implode("\n");
        $this->turnoutTimes = implode(', ', $election->turnout_times ?? []);
    }

    public function save(ElectionSetup $setup): void
    {
        $this->validate(['name' => ['required', 'string', 'min:3', 'max:150'], 'date' => ['required', 'date'], 'parties' => ['nullable', 'string', 'max:5000'], 'checklist' => ['required', 'string', 'max:5000']],
            [], ['name' => __('election.election_name'), 'date' => __('election.election_date')]);

        $parties = collect(preg_split('/\R/', $this->parties) ?: [])->map(fn ($l) => trim($l))->filter()
            ->map(fn ($l) => ['code' => (string) strtok($l, " \t"), 'name' => trim((string) mb_substr($l, mb_strlen((string) strtok($l, " \t"))))])->values()->all();

        try {
            $election = $setup->save($this->actor(), [
                'name' => $this->name, 'election_date' => $this->date,
                'checklist' => preg_split('/\R/', $this->checklist) ?: [],
                'turnout_times' => array_map('trim', explode(',', $this->turnoutTimes)),
            ], $parties, $this->electionId ? Election::query()->with('parties')->findOrFail($this->electionId) : null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? collect($e->errors()));

            return;
        }

        $this->electionId = $election->id;
        $this->dispatch('toast', type: 'success', title: __('election.setup_saved'));
    }

    public function activate(ElectionSetup $setup): void
    {
        $this->changeStatus(fn (Election $e) => $setup->activate($e, $this->actor()), __('election.activated'));
    }

    public function close(ElectionSetup $setup): void
    {
        $this->changeStatus(fn (Election $e) => $setup->close($e, $this->actor()), __('election.closed_ok'));
    }

    private function changeStatus(callable $action, string $success): void
    {
        try {
            $action(Election::query()->findOrFail($this->electionId));
        } catch (ValidationException|AuthorizationDenied $e) {
            $this->dispatch('toast', type: 'error', title: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return;
        }
        $this->dispatch('toast', type: 'success', title: $success);
    }

    public function render(): View
    {
        return view('livewire.workspace.election.election-setup', [
            'elections' => Election::query()->withCount('parties')->latest('election_date')->get(),
            'current' => $this->electionId ? Election::query()->find($this->electionId) : null,
        ])->title(__('election.setup'))->layoutData(['breadcrumbs' => [__('election.title') => route('workspace.election.hub'), __('election.setup') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
