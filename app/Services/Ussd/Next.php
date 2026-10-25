<?php

declare(strict_types=1);

namespace App\Services\Ussd;

/** What a screen wants after handling input: go to another screen, end the session, or ask again. */
final readonly class Next
{
    private function __construct(public string $kind, public ?string $screen = null, public ?string $text = null) {}

    public static function to(string $screen): self
    {
        return new self('to', $screen);
    }

    public static function end(string $text): self
    {
        return new self('end', text: $text);
    }

    public static function retry(string $error): self
    {
        return new self('retry', text: $error);
    }
}
