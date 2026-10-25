<?php

declare(strict_types=1);

namespace App\Services\Ussd;

final readonly class UssdReply
{
    private function __construct(public string $text, public bool $end) {}

    public static function con(string $text): self
    {
        return new self($text, false);
    }

    public static function end(string $text): self
    {
        return new self($text, true);
    }

    /** Africa's Talking wire format. */
    public function toProviderString(): string
    {
        return ($this->end ? 'END ' : 'CON ').$this->text;
    }
}
