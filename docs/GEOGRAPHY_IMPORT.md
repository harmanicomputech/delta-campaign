# Geographic reference data and imports

## What is seeded

`php artisan db:seed` (the `GeographySeeder`) creates the three senatorial districts and 25 LGAs from the
project brief, using internal codes (`DN`, `DC`, `DS`; LGA slugs). Official INEC codes (`lgas.inec_code`)
are added only from supplied reference data.

**Wards and polling units are not seeded.** They must come from official reference data, imported as
described below. For development only, `php artisan db:seed --class=SampleDataSeeder` creates
3 SAMPLE wards per LGA and 4 SAMPLE polling units per ward, all coded `SMP-…` and flagged `is_sample`.
It refuses to run in production.

## CSV format

UTF-8 CSV with a header row. Header names are case- and punctuation-insensitive (`PU Code` = `pu_code`).

| Column | Required | Notes |
| --- | --- | --- |
| `lga` | yes | LGA name (`Oshimili South`), slug (`oshimili-south`) or INEC code once recorded |
| `ward_code` | yes | Official ward code, unique within the LGA. Upper-cased |
| `ward_name` | yes | |
| `pu_code` | yes | Official polling-unit code, unique statewide. Letters, digits, `-`, `/`, `.` |
| `pu_name` | yes | |
| `latitude`, `longitude` | no | Decimal degrees, both or neither; must fall inside the Delta State bounding box |
| `registered_voters` | no | Only from official data; whole number 0–100,000 |

Limit: 50,000 rows per file (`IMPORTS_MAX_ROWS`).

## Lifecycle

```
uploaded → validating → validated (preview) → importing → completed
                     ↘ failed                ↘ cancelled   ↘ failed
```

1. **Upload/start.** The file is stored privately with its SHA-256 hash, and validation is queued
   (`imports` queue, `redis-long` connection).
2. **Validate.** Every row is checked and stored with its errors, warnings and planned action
   (create / update / unchanged). Nothing is written to the geography tables yet. An error report
   CSV is produced, with spreadsheet-formula injection neutralised.
3. **Preview.** Counts, row errors and warnings, plus a notice if a byte-identical file was imported before.
4. **Confirm.** Only validated imports with at least one valid row. Invalid rows are never applied.
5. **Apply.** Rows are re-validated against current data, because data may have changed since the
   preview, and applied in transactional chunks of 500. Only one geography import applies at a time
   (a lock; a waiting job retries).

### Rules

| Situation | Result |
| --- | --- |
| Unknown LGA, missing required value, bad code characters, half coordinates, non-numeric or out-of-state coordinates, bad voter count | Row error |
| Same `pu_code` twice in a file | Error on the later row |
| Same ward code with different names in one file | Error on the later row |
| Existing ward with a different name | Warning; the ward is renamed |
| Same PU name twice in a ward with different codes | Warning (possible duplicate) |
| `pu_code` that already exists in a different ward | Error: moves are not done by import |
| Re-import of identical data | All rows `unchanged`; nothing written |
| Blank optional value for an existing unit | Existing value kept (blanks never erase) |
| Unit present in the database but missing from the file | Kept; imports never delete |

## Command line

```bash
php artisan geography:import path/to/units.csv            # validate and print the summary and first errors
php artisan geography:import path/to/units.csv --confirm  # validate, then apply
php artisan geography:import path/to/units.csv --confirm --sample   # flag created rows as SAMPLE
```

The workspace UI for uploads, previews and history is delivered with administration (Phase 6).
