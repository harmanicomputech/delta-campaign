# Data model

PostgreSQL 16 + PostGIS. Integrity rules live in the database where practical (foreign keys, unique and
check constraints, triggers), not only in application code.

## Geography (Phase 2)

| Table | Key columns | Integrity |
| --- | --- | --- |
| `senatorial_districts` | `code` (internal: DN/DC/DS), `name` | unique code and name |
| `lgas` | `senatorial_district_id`, `slug`, `name`, `inec_code?` | unique slug, name, INEC code; delete restricted while wards exist |
| `wards` | `lga_id`, `senatorial_district_id`*, `code`, `name`, `is_sample` | unique (`lga_id`, `code`); trigram index on name |
| `polling_units` | `ward_id`, `lga_id`*, `senatorial_district_id`*, `code`, `name`, `latitude`, `longitude`, `location` (generated `geography(Point,4326)`), `registered_voters?`, `is_sample`, `import_job_id?` | unique code; coordinates both-or-neither with range checks; GiST index on location; trigram index on name |
| `import_jobs` | `type`, `status`, private `path`, `file_hash`, row and outcome counts, `error_report_path`, created/confirmed by | |
| `import_rows` | `import_job_id`, `row_number`, `payload`, `status`, `action`, `errors`, `warnings` | unique (`import_job_id`, `row_number`) |

\* Denormalised for single-predicate geographic scoping. Maintained by triggers: set from the parent on
insert/update (so they cannot be forged), and cascaded when a ward moves LGA or an LGA moves district.
