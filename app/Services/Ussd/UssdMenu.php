<?php

declare(strict_types=1);

namespace App\Services\Ussd;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Services\Ussd\Screens\OptionMenu;
use App\Services\Ussd\Screens\PagedList;
use App\Services\Ussd\Screens\Screen;
use App\Services\Ussd\Screens\TextInput;
use App\Support\Phone\NigerianPhoneNumber;
use Throwable;

/**
 * The USSD state machine. Each callback carries the cumulative input
 * ("1*2*Ada"); only the newest segment is processed against the state saved for
 * the session. Navigation everywhere: "0" back, "00" main menu, "98" more.
 *
 *   language → main → 1 join (name → LGA → SMS updates → 18+)
 *                     2 volunteer (name → LGA → ward)
 *                     3 polling unit (by code, or LGA → ward → unit)
 *                     4 projects in my LGA
 *                     5 call back (topic → LGA)
 *                     6 messages and my data (stop / resume / delete → confirm)
 */
final class UssdMenu
{
    /** @var array<string, Screen>|null */
    private ?array $screens = null;

    public function __construct(private readonly UssdActions $actions, private readonly UssdSessionStore $store) {}

    public function handle(UssdRequest $request, bool $simulated = false): UssdReply
    {
        $phone = NigerianPhoneNumber::normalise($request->phone);
        if ($phone === null) {
            return UssdReply::end(__('ussd.unsupported_number'));
        }

        $session = $this->store->load($request->sessionId);
        $text = trim($request->text);

        if ($session !== null && $session->lastText === $text && $session->lastReply !== null) {
            // A provider retry of the same step: answer identically, do nothing twice.
            return $this->decode($session->lastReply);
        }

        if ($session === null && $text !== '') {
            return UssdReply::end(__('ussd.expired'));
        }

        $session ??= new UssdSession($request->sessionId);
        $session->phone = $phone;
        $session->simulated = $simulated;

        try {
            $reply = $text === '' ? $this->show($session, 'language') : $this->step($session, $this->newestInput($text, $session->lastText));
        } catch (Throwable $e) {
            report($e);
            $reply = UssdReply::end($session->t('error'));
        }

        $reply = $this->fit($reply);
        $session->lastText = $text;
        $session->lastReply = $reply->toProviderString();
        $reply->end ? $this->store->forget($session->id) : $this->store->save($session);

        return $reply;
    }

    /** @return array<string, Screen> */
    public function screens(): array
    {
        return $this->screens ??= $this->build();
    }

    private function step(UssdSession $session, string $input): UssdReply
    {
        $screen = $this->screens()[$session->screen];

        if ($input === '00' && ! in_array($session->screen, ['language', 'main'], true)) {
            $session->history = ['language'];

            return $this->show($session, 'main');
        }

        if ($input === '0' && $screen->allowsBack()) {
            if ($screen->previousPage($session)) {
                return UssdReply::con($screen->prompt($session));
            }
            $previous = array_pop($session->history) ?? 'main';

            return $this->show($session, $previous, remember: false);
        }

        if ($input === '98' && $screen->nextPage($session)) {
            return UssdReply::con($screen->prompt($session));
        }

        $next = $screen->submit($session, $input);

        return match ($next->kind) {
            'to' => $this->show($session, (string) $next->screen),
            'end' => UssdReply::end((string) $next->text),
            default => $this->retry($session, $screen, (string) $next->text),
        };
    }

    private function retry(UssdSession $session, Screen $screen, string $error): UssdReply
    {
        if (! $screen->handlesErrors()) {
            return UssdReply::con($error."\n".$screen->prompt($session));
        }

        $session->error = $error;
        $prompt = $screen->prompt($session);
        $session->error = null;

        return UssdReply::con($prompt);
    }

    private function show(UssdSession $session, string $key, bool $remember = true): UssdReply
    {
        if ($remember && $session->screen !== $key && $session->lastText !== '') {
            $session->history[] = $session->screen;
        }

        $session->screen = $key;
        $screen = $this->screens()[$key];
        $screen->enter($session);

        return UssdReply::con($screen->prompt($session));
    }

    /** Safety net: screens are designed (and tested) to fit; anything longer is cut rather than rejected by the network. */
    private function fit(UssdReply $reply): UssdReply
    {
        $max = (int) config('campaign.ussd.max_screen');

        if (mb_strlen($reply->text) <= $max) {
            return $reply;
        }

        report(new \LengthException('USSD screen over '.$max.' characters.'));
        $text = mb_substr($reply->text, 0, $max);

        return $reply->end ? UssdReply::end($text) : UssdReply::con($text);
    }

    private function newestInput(string $text, string $previous): string
    {
        if ($previous !== '' && str_starts_with($text, $previous.'*')) {
            return substr($text, strlen($previous) + 1);
        }

        $parts = explode('*', $text);

        return (string) end($parts);
    }

    private function decode(string $wire): UssdReply
    {
        return str_starts_with($wire, 'END ') ? UssdReply::end(substr($wire, 4)) : UssdReply::con(substr($wire, 4));
    }

    /** @return array<string, Screen> */
    private function build(): array
    {
        $a = $this->actions;
        $name = fn (string $value): ?string => preg_match("/^[\\p{L}][\\p{L} .'-]{1,59}$/u", $value) === 1 ? null : 'invalid_name';
        $lgaName = fn (UssdSession $s): Lga => Lga::query()->findOrFail((int) $s->data['lga_id']);

        $screens = [
            new OptionMenu('language', 'welcome', ['english', 'pidgin'], function (UssdSession $s, int $n): Next {
                $s->lang = $n === 2 ? 'pcm' : 'en';

                return Next::to('main');
            }, back: false, replace: fn () => ['name' => (string) config('campaign.ussd.name')]),

            new OptionMenu('main', 'main_title', ['main_join', 'main_volunteer', 'main_pu', 'main_projects', 'main_callback', 'main_prefs'],
                fn (UssdSession $s, int $n): Next => Next::to(['join.name', 'volunteer.name', 'pu.method', 'projects.lga', 'callback.topic', 'prefs.menu'][$n - 1])),

            // 1. Join as a supporter
            new TextInput('join.name', 'ask_name', $name, function (UssdSession $s, string $v): Next {
                $s->data['name'] = $v;

                return Next::to('join.lga');
            }),
            new PagedList('join.lga', 'choose_lga', fn () => $a->lgas(), function (UssdSession $s, int|string $id): Next {
                $s->data['lga_id'] = (int) $id;

                return Next::to('join.updates');
            }),
            new OptionMenu('join.updates', 'ask_updates', ['yes', 'no'], function (UssdSession $s, int $n): Next {
                $s->data['sms'] = $n === 1;

                return Next::to('join.adult');
            }),
            new OptionMenu('join.adult', 'ask_adult', ['yes', 'no'], fn (UssdSession $s, int $n): Next => $n === 1 ? $a->join($s) : Next::end($s->t('must_be_adult'))),

            // 2. Volunteer registration
            new TextInput('volunteer.name', 'ask_name', $name, function (UssdSession $s, string $v): Next {
                $s->data['name'] = $v;

                return Next::to('volunteer.lga');
            }),
            new PagedList('volunteer.lga', 'choose_lga', fn () => $a->lgas(), function (UssdSession $s, int|string $id) use ($a): Next {
                $s->data['lga_id'] = (int) $id;
                unset($s->data['ward_id']);

                return $a->wards((int) $id) === [] ? $a->volunteer($s) : Next::to('volunteer.ward');
            }),
            new PagedList('volunteer.ward', 'choose_ward', fn (UssdSession $s) => $a->wards((int) $s->data['lga_id']), function (UssdSession $s, int|string $id) use ($a): Next {
                $s->data['ward_id'] = (int) $id;

                return $a->volunteer($s);
            }),

            // 3. Find my polling unit
            new OptionMenu('pu.method', 'pu_title', ['pu_by_code', 'pu_browse'], fn (UssdSession $s, int $n): Next => Next::to($n === 1 ? 'pu.code' : 'pu.lga')),
            new TextInput('pu.code', 'ask_pu_code', fn (string $v): ?string => preg_match('/^[A-Za-z0-9\/.\-]{2,40}$/', $v) === 1 ? null : 'invalid_code',
                fn (UssdSession $s, string $v): Next => ($unit = $a->findUnit($v)) ? $a->pollingUnit($s, $unit) : Next::retry($s->t('pu_not_found'))),
            new PagedList('pu.lga', 'choose_lga', fn () => $a->lgas(), function (UssdSession $s, int|string $id): Next {
                $s->data['lga_id'] = (int) $id;

                return Next::to('pu.ward');
            }),
            new PagedList('pu.ward', 'choose_ward', fn (UssdSession $s) => $a->wards((int) $s->data['lga_id']), function (UssdSession $s, int|string $id): Next {
                $s->data['ward_id'] = (int) $id;

                return Next::to('pu.unit');
            }, 'no_wards'),
            new PagedList('pu.unit', 'choose_unit', fn (UssdSession $s) => $a->units((int) $s->data['ward_id']),
                fn (UssdSession $s, int|string $id): Next => $a->pollingUnit($s, PollingUnit::query()->findOrFail((int) $id)), 'no_units'),

            // 4. Approved projects in my LGA
            new PagedList('projects.lga', 'choose_lga', fn () => $a->lgas(), function (UssdSession $s, int|string $id) use ($a, $lgaName): Next {
                $s->data['lga_id'] = (int) $id;

                return $a->projects($s, $lgaName($s));
            }),

            // 5. Request a callback
            new OptionMenu('callback.topic', 'callback_title', ['topic_general', 'topic_volunteering', 'topic_problem'], function (UssdSession $s, int $n): Next {
                $s->data['topic'] = ['general', 'volunteering', 'problem'][$n - 1];

                return Next::to('callback.lga');
            }),
            new PagedList('callback.lga', 'choose_lga', fn () => $a->lgas(), function (UssdSession $s, int|string $id) use ($a): Next {
                $s->data['lga_id'] = (int) $id;

                return $a->callback($s);
            }),

            // 6. Communication preferences and data deletion
            new OptionMenu('prefs.menu', 'prefs_title', ['prefs_stop', 'prefs_resume', 'prefs_delete'], function (UssdSession $s, int $n): Next {
                $s->data['pref'] = ['stop', 'resume', 'delete'][$n - 1];

                return Next::to('prefs.confirm');
            }),
            new OptionMenu('prefs.confirm', 'prefs_confirm', ['yes', 'no'], fn (UssdSession $s, int $n): Next => $n === 1 ? $a->preferences($s) : Next::end($s->t('cancelled')),
                replace: fn (UssdSession $s) => ['action' => $s->t('prefs_'.($s->data['pref'] ?? 'stop'))]),
        ];

        return collect($screens)->keyBy(fn (Screen $screen) => $screen->key())->all();
    }
}
