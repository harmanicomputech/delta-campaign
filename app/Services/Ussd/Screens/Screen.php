<?php

declare(strict_types=1);

namespace App\Services\Ussd\Screens;

use App\Services\Ussd\Next;
use App\Services\Ussd\UssdSession;

/** One step of the USSD menu. Prompts must fit the 182-character screen (tested). */
abstract class Screen
{
    abstract public function key(): string;

    abstract public function prompt(UssdSession $session): string;

    abstract public function submit(UssdSession $session, string $input): Next;

    /** Whether "0" goes back from this screen. */
    public function allowsBack(): bool
    {
        return true;
    }

    /** "98" is handled by paged screens; others ignore it. */
    public function nextPage(UssdSession $session): bool
    {
        return false;
    }

    /** "0" on a later page goes to the previous page before leaving the screen. */
    public function previousPage(UssdSession $session): bool
    {
        return false;
    }

    /** Whether prompt() places $session->error itself (otherwise the menu puts it on top). */
    public function handlesErrors(): bool
    {
        return false;
    }

    /** Called when the screen is entered (fresh or via back). */
    public function enter(UssdSession $session): void {}

    /** @param list<string> $options */
    protected function menu(string $title, array $options): string
    {
        $lines = [$title];
        foreach (array_values($options) as $i => $option) {
            $lines[] = ($i + 1).'. '.$option;
        }

        return implode("\n", $lines);
    }

    protected function choice(string $input, int $count): ?int
    {
        return ctype_digit($input) && (int) $input >= 1 && (int) $input <= $count ? (int) $input : null;
    }
}
