<?php

declare(strict_types=1);

namespace App\Services\Ussd\Screens;

use App\Services\Ussd\Next;
use App\Services\Ussd\UssdSession;
use Closure;

/** A paged list built from closures (LGAs, wards, polling units). */
final class PagedList extends PagedChoice
{
    /**
     * @param  Closure(UssdSession): array<int|string, string>  $items
     * @param  Closure(UssdSession, int|string): Next  $choose
     */
    public function __construct(
        private readonly string $key,
        private readonly string $titleKey,
        private readonly Closure $items,
        private readonly Closure $choose,
        private readonly ?string $emptyKey = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    protected function items(UssdSession $session): array
    {
        return ($this->items)($session);
    }

    protected function title(UssdSession $session): string
    {
        return $session->t($this->titleKey);
    }

    protected function choose(UssdSession $session, int|string $id): Next
    {
        return ($this->choose)($session, $id);
    }

    protected function emptyText(UssdSession $session): string
    {
        return $session->t($this->emptyKey ?? 'nothing_found');
    }
}
