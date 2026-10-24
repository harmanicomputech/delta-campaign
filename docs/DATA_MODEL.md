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

## Identity and access (Phase 3)

| Table | Notes |
| --- | --- |
| `users` | Phone identity: `phone_encrypted`, unique `phone_hash`, `phone_last4`; status; TOTP secret and recovery codes (encrypted); PIN hash and lockout; suspension fields |
| `roles`, `permissions`, `permission_role` | Seeded from `config/permissions.php` |
| `role_assignments` | `user_id`, `role_id`, `scope_type`, `scope_id`, `revoked_at`. Check constraint: state scope has no id, others require one. Partial unique index on active assignments |
| `otp_challenges` | HMAC of the code, attempts, expiry, consumed |
| `devices`, `login_events` | Keyed cookie hash; full sign-in history |
| `sessions` | Database sessions with `device_id` |
| `audit_logs` | Append-only (triggers), `previous_hash`/`hash` chain |

## Supporters and data protection (Phase 4)

| Table | Notes |
| --- | --- |
| `supporters` | Reference, name, encrypted phone and keyed hash (unique, cleared on erasure), geography derived by trigger from the most specific location, verification, 18+ attestation, retention, legal hold, `version` (optimistic locking). Check constraint: an erased record holds no contact data. Trigram name index |
| `consent_records` | Append-only (trigger): phone hash, channel, purpose, status, notice version (FK), source, evidence |
| `suppressions` | Phone hash, channel (null = all), reason; partial unique index on active rows |
| `privacy_notices` | Versioned notices (EN/Pidgin); the latest published applies |
| `data_subject_requests` | Reference, type, status, channel, encrypted contact, due date, identity verification, resolution |
| `contact_attempts` | Client UUID (idempotent sync), encrypted notes, denormalised geography, quality flag |
| `export_jobs` | Queued, scoped exports with expiry and download count |
| `workflow_transitions` | Generic status history |
| `system_settings` | Audited runtime settings (`config/settings.php`) |
