<?php

declare(strict_types=1);

namespace App\Services\Ussd;

/**
 * State of one USSD session between callbacks. Serialised (encrypted) to the
 * cache; the caller's number is attached per request and never stored here.
 */
final class UssdSession
{
    public string $lang = 'en';

    public string $screen = 'language';

    /** @var list<string> */
    public array $history = [];

    /** @var array<string, mixed> */
    public array $data = [];

    public int $page = 0;

    public ?string $error = null;

    public string $lastText = '';

    public ?string $lastReply = null;

    /** Set per request, not persisted. */
    public string $phone = '';

    /** Practice mode (simulator): created records are marked SAMPLE. */
    public bool $simulated = false;

    public function __construct(public readonly string $id) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['lang' => $this->lang, 'screen' => $this->screen, 'history' => $this->history, 'data' => $this->data, 'page' => $this->page, 'lastText' => $this->lastText, 'lastReply' => $this->lastReply];
    }

    /** @param array<string, mixed> $state */
    public static function fromArray(string $id, array $state): self
    {
        $session = new self($id);
        foreach (['lang', 'screen', 'history', 'data', 'page', 'lastText', 'lastReply'] as $key) {
            if (array_key_exists($key, $state)) {
                $session->{$key} = $state[$key];
            }
        }

        return $session;
    }

    public function t(string $key, array $replace = []): string
    {
        return __('ussd.'.$key, $replace, $this->lang);
    }
}
