<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/** Reads "<field>_<locale>" columns with an English fallback (Pidgin first when the visitor chose it). */
trait HasTranslations
{
    public function translated(string $field, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $value = $locale === 'pcm' ? $this->getAttribute($field.'_pcm') : null;

        return (string) (filled($value) ? $value : $this->getAttribute($field.'_en'));
    }
}
