<?php

declare(strict_types=1);

namespace App\Services\Imports;

use Generator;
use RuntimeException;
use SplFileObject;

/**
 * Streams a CSV file row by row (constant memory), keyed by normalised header.
 * Handles UTF-8 BOMs, blank lines and CRLF line endings.
 */
final class CsvReader
{
    /** @var list<string> */
    private array $header = [];

    public function __construct(private readonly string $path) {}

    /**
     * @return list<string>
     */
    public function header(): array
    {
        if ($this->header === []) {
            $file = $this->open();
            $row = $file->fgetcsv(escape: '');
            $this->header = array_map(self::normaliseHeader(...), is_array($row) ? array_map('strval', $row) : []);
        }

        return $this->header;
    }

    /**
     * @return Generator<int, array<string, string>> Row number (1 = first data row) => values by header.
     */
    public function rows(): Generator
    {
        $header = $this->header();
        $file = $this->open();
        $file->fgetcsv(escape: '');
        $number = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv(escape: '');

            if (! is_array($row) || $row === [null] || implode('', array_map('strval', $row)) === '') {
                continue;
            }

            $number++;
            $values = array_map(fn ($value): string => self::clean((string) $value), $row);
            $values = array_pad(array_slice($values, 0, count($header)), count($header), '');

            yield $number => array_combine($header, $values);
        }
    }

    public static function normaliseHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value))), '_');
    }

    /** Trims and collapses internal whitespace; strips control characters. */
    public static function clean(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? $value;

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function open(): SplFileObject
    {
        if (! is_readable($this->path)) {
            throw new RuntimeException('Import file is not readable.');
        }

        return new SplFileObject($this->path, 'r');
    }
}
