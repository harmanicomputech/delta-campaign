<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Enums\ImportRowAction;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Support\Str;

/**
 * Validates one row of a ward/polling-unit import against the current
 * reference data and the rows already seen in the same file.
 *
 * Errors block the row; warnings are shown in the preview but do not block.
 */
final class PollingUnitRowValidator
{
    public const array REQUIRED_COLUMNS = ['lga', 'ward_code', 'ward_name', 'pu_code', 'pu_name'];

    public const array OPTIONAL_COLUMNS = ['latitude', 'longitude', 'registered_voters'];

    /** @var array<string, int> LGA lookup by normalised name, slug or INEC code. */
    private array $lgas = [];

    /** @var array<string, array{id: int, ward_id: int, lga_id: int, name: string, latitude: string|null, longitude: string|null, registered_voters: int|null}> */
    private array $units = [];

    /** @var array<string, array{id: int, name: string}> Existing wards by "lga_id|code". */
    private array $wards = [];

    /** @var array<string, int> pu_code => first row number in this file. */
    private array $seenCodes = [];

    /** @var array<string, string> "lga_id|ward_code" => ward name first seen in this file. */
    private array $seenWards = [];

    /** @var array<string, int> "lga_id|ward_code|pu name" => first row number. */
    private array $seenNames = [];

    /**
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bounds
     */
    public function __construct(private readonly array $bounds)
    {
        foreach (Lga::query()->get(['id', 'name', 'slug', 'inec_code']) as $lga) {
            $this->lgas[self::key($lga->name)] = $lga->id;
            $this->lgas[self::key($lga->slug)] = $lga->id;
            if ($lga->inec_code) {
                $this->lgas[self::key($lga->inec_code)] = $lga->id;
            }
        }

        foreach (Ward::query()->get(['id', 'lga_id', 'code', 'name']) as $ward) {
            $this->wards[$ward->lga_id.'|'.$ward->code] = ['id' => $ward->id, 'name' => $ward->name];
        }

        PollingUnit::query()
            ->select(['id', 'ward_id', 'lga_id', 'code', 'name', 'latitude', 'longitude', 'registered_voters'])
            ->orderBy('id')
            ->chunk(2000, function ($chunk): void {
                foreach ($chunk as $unit) {
                    $this->units[$unit->code] = [
                        'id' => $unit->id,
                        'ward_id' => $unit->ward_id,
                        'lga_id' => $unit->lga_id,
                        'name' => $unit->name,
                        'latitude' => $unit->latitude,
                        'longitude' => $unit->longitude,
                        'registered_voters' => $unit->registered_voters,
                    ];
                }
            });
    }

    /**
     * @param  list<string>  $header
     * @return list<string> Missing required columns.
     */
    public static function missingColumns(array $header): array
    {
        return array_values(array_diff(self::REQUIRED_COLUMNS, $header));
    }

    /**
     * @param  array<string, string>  $row
     * @return array{errors: list<string>, warnings: list<string>, action: ImportRowAction|null, normalised: array<string, string|int|float|null>}
     */
    public function validate(int $rowNumber, array $row): array
    {
        $errors = [];
        $warnings = [];

        $wardCode = Str::upper($row['ward_code'] ?? '');
        $puCode = Str::upper($row['pu_code'] ?? '');
        $wardName = $row['ward_name'] ?? '';
        $puName = $row['pu_name'] ?? '';

        $lgaId = $this->lgas[self::key($row['lga'] ?? '')] ?? null;
        if (($row['lga'] ?? '') === '') {
            $errors[] = 'LGA is required.';
        } elseif ($lgaId === null) {
            $errors[] = "Unknown LGA \"{$row['lga']}\". Use one of the 25 LGA names.";
        }

        foreach (['ward_code' => $wardCode, 'ward_name' => $wardName, 'pu_code' => $puCode, 'pu_name' => $puName] as $column => $value) {
            if ($value === '') {
                $errors[] = str_replace('_', ' ', ucfirst($column)).' is required.';
            }
        }

        foreach (['ward_code' => [$wardCode, 30], 'pu_code' => [$puCode, 40], 'ward_name' => [$wardName, 150], 'pu_name' => [$puName, 255]] as $column => [$value, $max]) {
            if (mb_strlen($value) > $max) {
                $errors[] = str_replace('_', ' ', ucfirst($column))." must be at most {$max} characters.";
            }
        }

        if ($puCode !== '' && preg_match('/^[A-Z0-9][A-Z0-9\/\-.]*$/', $puCode) !== 1) {
            $errors[] = 'PU code may contain only letters, digits, "-", "/" and ".".';
        }

        [$latitude, $longitude] = $this->coordinates($row, $errors);
        $voters = $this->voters($row['registered_voters'] ?? '', $errors);

        // Duplicates within the file.
        if ($puCode !== '' && isset($this->seenCodes[$puCode])) {
            $errors[] = "Duplicate PU code {$puCode}: already on row {$this->seenCodes[$puCode]}.";
        }

        if ($lgaId !== null && $wardCode !== '') {
            $wardKey = $lgaId.'|'.$wardCode;
            if (isset($this->seenWards[$wardKey]) && self::key($this->seenWards[$wardKey]) !== self::key($wardName)) {
                $errors[] = "Ward {$wardCode} is named \"{$this->seenWards[$wardKey]}\" on an earlier row.";
            }
            if (isset($this->wards[$wardKey]) && self::key($this->wards[$wardKey]['name']) !== self::key($wardName)) {
                $warnings[] = "Ward {$wardCode} will be renamed from \"{$this->wards[$wardKey]['name']}\".";
            }

            $nameKey = $wardKey.'|'.self::key($puName);
            if ($puName !== '' && isset($this->seenNames[$nameKey]) && ! isset($this->seenCodes[$puCode])) {
                $warnings[] = "Possible duplicate: same PU name as row {$this->seenNames[$nameKey]} in this ward with a different code.";
            }
        }

        // Against existing data.
        $action = null;
        if ($errors === [] && $lgaId !== null) {
            $existing = $this->units[$puCode] ?? null;
            $wardId = $this->wards[$lgaId.'|'.$wardCode]['id'] ?? null;

            if ($existing === null) {
                $action = ImportRowAction::Create;
            } elseif ($existing['lga_id'] !== $lgaId || ($wardId !== null && $existing['ward_id'] !== $wardId) || $wardId === null) {
                $errors[] = "PU code {$puCode} already belongs to a different ward. Moving polling units is not done by import.";
            } else {
                $changed = $existing['name'] !== $puName
                    || self::differs($existing['latitude'], $latitude)
                    || self::differs($existing['longitude'], $longitude)
                    || ($voters !== null && $existing['registered_voters'] !== $voters)
                    || (isset($this->wards[$lgaId.'|'.$wardCode]) && self::key($this->wards[$lgaId.'|'.$wardCode]['name']) !== self::key($wardName));

                $action = $changed ? ImportRowAction::Update : ImportRowAction::Unchanged;
            }
        }

        if ($puCode !== '' && ! isset($this->seenCodes[$puCode])) {
            $this->seenCodes[$puCode] = $rowNumber;
        }
        if ($lgaId !== null && $wardCode !== '') {
            $this->seenWards[$lgaId.'|'.$wardCode] ??= $wardName;
            $this->seenNames[$lgaId.'|'.$wardCode.'|'.self::key($puName)] ??= $rowNumber;
        }

        return [
            'errors' => $errors === [] ? [] : array_values(array_unique($errors)),
            'warnings' => $warnings,
            'action' => $errors === [] ? $action : null,
            'normalised' => [
                'lga_id' => $lgaId,
                'ward_code' => $wardCode,
                'ward_name' => $wardName,
                'pu_code' => $puCode,
                'pu_name' => $puName,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'registered_voters' => $voters,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $errors
     * @return array{0: float|null, 1: float|null}
     */
    private function coordinates(array $row, array &$errors): array
    {
        $lat = $row['latitude'] ?? '';
        $lng = $row['longitude'] ?? '';

        if ($lat === '' && $lng === '') {
            return [null, null];
        }

        if ($lat === '' || $lng === '') {
            $errors[] = 'Latitude and longitude must be given together.';

            return [null, null];
        }

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            $errors[] = 'Latitude and longitude must be decimal numbers.';

            return [null, null];
        }

        $latitude = round((float) $lat, 6);
        $longitude = round((float) $lng, 6);
        $b = $this->bounds;

        if ($latitude < $b['min_lat'] || $latitude > $b['max_lat'] || $longitude < $b['min_lng'] || $longitude > $b['max_lng']) {
            $errors[] = "Coordinates ({$latitude}, {$longitude}) are outside Delta State. Check that latitude and longitude are not swapped.";
        }

        return [$latitude, $longitude];
    }

    /**
     * @param  list<string>  $errors
     */
    private function voters(string $value, array &$errors): ?int
    {
        if ($value === '') {
            return null;
        }

        $digits = str_replace([',', ' '], '', $value);
        if (! ctype_digit($digits) || (int) $digits > 100_000) {
            $errors[] = 'Registered voters must be a whole number between 0 and 100,000.';

            return null;
        }

        return (int) $digits;
    }

    private static function differs(?string $current, ?float $incoming): bool
    {
        if ($incoming === null) {
            return false; // Blank coordinates never erase existing ones.
        }

        return $current === null || abs((float) $current - $incoming) > 0.0000005;
    }

    private static function key(string $value): string
    {
        return Str::lower((string) preg_replace('/[^a-z0-9]+/i', '', Str::ascii($value)));
    }
}
