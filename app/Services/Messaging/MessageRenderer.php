<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Supporter;

/**
 * Renders templates with a fixed set of placeholders and counts SMS segments
 * (GSM-7: 160 / 153 per part; anything else is UCS-2: 70 / 67 per part).
 */
final class MessageRenderer
{
    public const array PLACEHOLDERS = ['first_name', 'lga', 'ward', 'reference'];

    /** Longest values assumed when estimating segments before real recipients are known. */
    private const array WORST_CASE = ['first_name' => 'Oghenekevwe-Mary', 'lga' => 'Warri South West', 'ward' => 'Longest Sample Ward Name Here', 'reference' => 'SUP-XXXXXXXX'];

    private const string GSM7 = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const string GSM7_EXTENDED = '^{}\\[~]|€';

    /** @return list<string> placeholders that are not allowed */
    public function unknownPlaceholders(string $body): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $body, $m);

        return array_values(array_diff(array_unique($m[1]), self::PLACEHOLDERS));
    }

    /** @return list<string> placeholders in order of first appearance (WhatsApp template parameters) */
    public function placeholders(string $body): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $body, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return array<string, string> */
    public function variables(Supporter $supporter): array
    {
        $supporter->loadMissing(['lga:id,name', 'ward:id,name']);

        return [
            'first_name' => (string) strtok($supporter->full_name, ' '),
            'lga' => (string) $supporter->lga?->name,
            'ward' => (string) $supporter->ward?->name,
            'reference' => $supporter->reference,
        ];
    }

    /** @param array<string, string> $variables */
    public function render(string $body, array $variables): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn (array $m): string => $variables[$m[1]] ?? '', $body);
    }

    public function worstCase(string $body): string
    {
        return $this->render($body, self::WORST_CASE);
    }

    public function segments(string $text): int
    {
        $length = 0;
        $gsm = true;

        foreach (mb_str_split($text) as $char) {
            if (str_contains(self::GSM7, $char)) {
                $length++;
            } elseif (str_contains(self::GSM7_EXTENDED, $char)) {
                $length += 2;
            } else {
                $gsm = false;
                break;
            }
        }

        if (! $gsm) {
            $length = mb_strlen($text);

            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    public function isGsm(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (! str_contains(self::GSM7, $char) && ! str_contains(self::GSM7_EXTENDED, $char)) {
                return false;
            }
        }

        return true;
    }
}
