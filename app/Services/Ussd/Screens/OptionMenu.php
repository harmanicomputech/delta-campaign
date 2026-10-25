<?php

declare(strict_types=1);

namespace App\Services\Ussd\Screens;

use App\Services\Ussd\Next;
use App\Services\Ussd\UssdSession;
use Closure;

/** A fixed numbered menu. Titles and options are translation keys under "ussd.". */
final class OptionMenu extends Screen
{
    /**
     * @param  list<string>  $options  translation keys
     * @param  Closure(UssdSession, int): Next  $choose  receives the 1-based choice
     * @param  (Closure(UssdSession): array<string, string|int>)|null  $replace  values for the title
     */
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly array $options,
        private readonly Closure $choose,
        private readonly bool $back = true,
        private readonly ?Closure $replace = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function allowsBack(): bool
    {
        return $this->back;
    }

    public function prompt(UssdSession $session): string
    {
        $title = $session->t($this->title, $this->replace ? ($this->replace)($session) : []);

        return $this->menu($title, array_map(fn (string $option): string => $session->t($option), $this->options));
    }

    public function submit(UssdSession $session, string $input): Next
    {
        $n = $this->choice($input, count($this->options));

        return $n === null ? Next::retry($session->t('invalid_choice')) : ($this->choose)($session, $n);
    }
}
