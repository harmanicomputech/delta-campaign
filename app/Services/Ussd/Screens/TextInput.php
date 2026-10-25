<?php

declare(strict_types=1);

namespace App\Services\Ussd\Screens;

use App\Services\Ussd\Next;
use App\Services\Ussd\UssdSession;
use Closure;

/** Free-text entry (a name, a polling-unit code). The validator returns an error key or null. */
final class TextInput extends Screen
{
    /**
     * @param  Closure(string): ?string  $validate
     * @param  Closure(UssdSession, string): Next  $then
     */
    public function __construct(
        private readonly string $key,
        private readonly string $promptKey,
        private readonly Closure $validate,
        private readonly Closure $then,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function prompt(UssdSession $session): string
    {
        return $session->t($this->promptKey)."\n0. ".$session->t('back');
    }

    public function submit(UssdSession $session, string $input): Next
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $input));
        $error = ($this->validate)($value);

        return $error !== null ? Next::retry($session->t($error)) : ($this->then)($session, $value);
    }
}
