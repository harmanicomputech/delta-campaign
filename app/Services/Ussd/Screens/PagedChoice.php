<?php

declare(strict_types=1);

namespace App\Services\Ussd\Screens;

use App\Services\Ussd\Next;
use App\Services\Ussd\UssdSession;

/**
 * A numbered list split into pages that each fit one screen (including any
 * error line shown above it). "98" shows more, "0" goes to the previous page
 * (or back from the first page). The items shown are remembered, so the
 * caller's number always selects what they saw.
 */
abstract class PagedChoice extends Screen
{
    /** @return array<int|string, string> id => label */
    abstract protected function items(UssdSession $session): array;

    abstract protected function title(UssdSession $session): string;

    abstract protected function choose(UssdSession $session, int|string $id): Next;

    protected function emptyText(UssdSession $session): string
    {
        return $session->t('nothing_found');
    }

    public function enter(UssdSession $session): void
    {
        $session->data['_pages'][$this->key()] = [0];
        unset($session->data['_shown'][$this->key()]);
    }

    public function handlesErrors(): bool
    {
        return true;
    }

    public function prompt(UssdSession $session): string
    {
        $items = $this->items($session);
        $title = ($session->error !== null ? $session->error."\n" : '').$this->title($session);

        if ($items === []) {
            return $title."\n".$this->emptyText($session)."\n0. ".$session->t('back');
        }

        [$lines, $ids, $end] = $this->page($session, $items, $title);
        $session->data['_shown'][$this->key()] = ['ids' => $ids, 'end' => $end, 'total' => count($items)];

        return implode("\n", $lines);
    }

    public function submit(UssdSession $session, string $input): Next
    {
        $ids = $session->data['_shown'][$this->key()]['ids'] ?? [];
        $n = $this->choice($input, count($ids));

        return $n === null ? Next::retry($session->t('invalid_choice')) : $this->choose($session, $ids[$n - 1]);
    }

    public function nextPage(UssdSession $session): bool
    {
        $shown = $session->data['_shown'][$this->key()] ?? null;

        if ($shown === null || $shown['end'] >= $shown['total']) {
            return false;
        }

        $session->data['_pages'][$this->key()][] = $shown['end'];

        return true;
    }

    public function previousPage(UssdSession $session): bool
    {
        $pages = $session->data['_pages'][$this->key()] ?? [0];

        if (count($pages) <= 1) {
            return false;
        }

        array_pop($pages);
        $session->data['_pages'][$this->key()] = $pages;

        return true;
    }

    /**
     * Fills the current page with as many items as fit.
     *
     * @param  array<int|string, string>  $items
     * @return array{0: list<string>, 1: list<int|string>, 2: int} lines, ids on the page, index after the page
     */
    private function page(UssdSession $session, array $items, string $title): array
    {
        $max = (int) config('campaign.ussd.max_screen');
        $pages = $session->data['_pages'][$this->key()] ?? [0];
        $start = (int) end($pages);
        $keys = array_keys($items);
        $more = '98. '.$session->t('more');
        $back = '0. '.$session->t('back');

        $lines = [$title];
        $ids = [];
        $i = $start;

        while ($i < count($keys)) {
            $line = (count($ids) + 1).'. '.mb_strimwidth($items[$keys[$i]], 0, 40, '…');
            $footer = $i + 1 < count($keys) ? [$more, $back] : [$back];

            if (mb_strlen(implode("\n", [...$lines, $line, ...$footer])) > $max || count($ids) >= 9) {
                break;
            }

            $lines[] = $line;
            $ids[] = $keys[$i];
            $i++;
        }

        if ($i < count($keys)) {
            $lines[] = $more;
        }
        $lines[] = $back;

        return [$lines, $ids, $i];
    }
}
