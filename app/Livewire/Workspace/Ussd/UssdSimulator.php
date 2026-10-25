<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Ussd;

use App\Models\User;
use App\Rules\NigerianMobileNumber;
use App\Services\Ussd\UssdActions;
use App\Services\Ussd\UssdMenu;
use App\Services\Ussd\UssdRequest;
use App\Services\Ussd\UssdSessionStore;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Browser USSD phone that runs the real menu. In practice mode (always in
 * production) every step runs inside a database transaction that is rolled
 * back, so nothing is saved and no SMS is queued.
 */
final class UssdSimulator extends Component
{
    public string $phone = '08170000099';

    public string $input = '';

    public bool $practice = true;

    #[Locked]
    public ?string $sessionId = null;

    #[Locked]
    public string $text = '';

    #[Locked]
    public ?string $screen = null;

    #[Locked]
    public bool $ended = false;

    /** @var list<array{input: string, screen: string, length: int, end: bool}> */
    #[Locked]
    public array $log = [];

    public function mount(): void
    {
        $this->authorize('ussd.simulate');
    }

    public function dial(): void
    {
        $this->authorize('ussd.simulate');
        $this->validate(['phone' => ['required', new NigerianMobileNumber]]);

        $this->sessionId = 'SIM-'.Str::uuid();
        $this->text = '';
        $this->log = [];
        $this->send(initial: true);
    }

    public function send(bool $initial = false): void
    {
        $this->authorize('ussd.simulate');

        if ($this->sessionId === null || ($this->ended && ! $initial)) {
            return;
        }

        if (! $initial) {
            $value = mb_substr(trim($this->input), 0, 100);
            if ($value === '') {
                return;
            }
            $this->text = $this->text === '' ? $value : $this->text.'*'.$value;
        }

        $menu = new UssdMenu(app(UssdActions::class), new UssdSessionStore('ussd-sim:'.$this->actor()->id));
        $practice = $this->practice || app()->isProduction();

        DB::beginTransaction();
        try {
            $reply = $menu->handle(new UssdRequest($this->sessionId, $this->phone, $this->text, '*SIM#'), simulated: true);
        } finally {
            $practice ? DB::rollBack() : DB::commit();
        }

        $this->screen = $reply->text;
        $this->ended = $reply->end;
        $this->log[] = ['input' => $initial ? '(dial)' : $this->input, 'screen' => $reply->text, 'length' => mb_strlen($reply->text), 'end' => $reply->end];
        $this->input = '';
    }

    public function hangUp(): void
    {
        $this->reset('sessionId', 'text', 'screen', 'ended', 'input');
    }

    public function render(): View
    {
        return view('livewire.workspace.ussd.ussd-simulator', [
            'max' => (int) config('campaign.ussd.max_screen'),
            'canSave' => ! app()->isProduction(),
        ])->title(__('simulator.title'))->layoutData(['breadcrumbs' => [__('nav.groups.communications') => null, __('simulator.title') => null]]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
