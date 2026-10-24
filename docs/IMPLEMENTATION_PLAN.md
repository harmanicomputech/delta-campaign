# Implementation Plan

Each phase is complete only when its acceptance criteria are met: functionality implemented,
migrations run, permissions and validation enforced, tests passing, UI verified in a browser (where
applicable), documentation updated and limitations recorded. Work stops at the end of each phase for
review.

Legend: ☐ not started · ◐ in progress · ☑ done

## Phase 0 — Repository audit and architecture ☑

- ☑ Inspect repository (empty; greenfield) — `docs/PROJECT_AUDIT.md`
- ☑ Architecture and design direction — `docs/ARCHITECTURE.md`
- ☑ Phased plan — this document

## Phase 1 — Laravel foundations and premium design system ☑

Completed 2026-10-24.

| Deliverable | Where | Verified by |
| --- | --- | --- |
| Laravel 13 on PostgreSQL 16 + PostGIS 3.4; Redis cache/queue; database sessions | `config/*.php`, `.env.example`, `database/migrations/*enable_postgis*` | `DatabaseTest` (PostGIS geography query) |
| Horizon with `realtime`, `messaging`, `default` and `bulk` supervisors; `redis-long` connection for long jobs | `config/horizon.php`, `config/queue.php` | `/health` queue check |
| Design tokens: provisional brand, light/dark/system themes, self-hosted Inter + Fraunces | `resources/css/app.css` | Browser screenshots in both themes |
| Component library (≈30 components) and Lucide icon pipeline | `resources/views/components/ui`, `scripts/` | `/design-system`, Playwright interaction checks |
| Workspace shell: collapsible sidebar, mobile drawer + bottom bar, breadcrumbs, command palette, offline indicator, toasts | `resources/views/layouts/app.blade.php`, `components/shell` | Playwright at 1440/834/390 px |
| Central navigation with "Planned · Phase N" entries | `config/navigation.php`, `App\Support\Navigation` | `NavigationTest` |
| Auth screen designs (phone, OTP) and auth layout | `pages/design-system/{sign-in,verify}` | Browser |
| Dashboard shell with live system status (Livewire, lazy, polling) | `pages/design-system/shell`, `App\Livewire\System\HealthStatusPanel` | `HealthStatusPanelTest`, browser |
| Public layout + holding page | `layouts/public`, `pages/public/home` | `PublicPagesTest` |
| Security headers + nonce CSP, request IDs, structured JSON logs with redaction | `app/Http/Middleware`, `app/Support/Logging` | `SecurityHeadersTest`, `RedactSensitiveDataTest` |
| `/health` readiness endpoint (no session, no internal details) | `HealthController`, `Services/Health` | `HealthEndpointTest` |
| Branded error pages (403/404/419/429/500/503) with request reference | `resources/views/errors` | `PublicPagesTest` |
| English + Pidgin locale switching | `lang/{en,pcm}`, `SetLocale`, `LocaleController` | `LocaleTest` (including key parity) |
| Nigerian mobile number normalisation + validation rule | `App\Support\Phone`, `App\Rules` | `NigerianPhoneNumberTest`, `SampleFormTest` |
| Docker image (web/horizon/scheduler roles) and compose stack | `Dockerfile`, `compose.yaml`, `docker/` | `docker compose config`; image build runs in CI |
| CI: Pint, PHPUnit (PHP 8.3/8.4, PostGIS, Redis), audits, Docker build | `.github/workflows/ci.yml` | Workflow YAML validated; first run pending push |

**Results at completion:** 81 tests / 203 assertions passing; Pint clean; `composer audit` and
`npm audit` clean; no console errors, CSP violations or horizontal overflow across 31 page/viewport/theme
screenshots; 24/24 browser interaction checks passing.

### Known limitations (Phase 1)

- **Docker image not built locally** (no Docker daemon in the development environment). The CI `docker` job is its first real build.
- **CI has not run yet.** It runs on the first push to GitHub.
- **No authentication yet.** Workspace pages are previews under `/design-system`, unavailable in production. Real `/login` and dashboards come in Phase 3.
- **Default `users` migration** from the Laravel skeleton is still present. It will be replaced by the phone-based identity schema in Phase 3 (nothing has been deployed, so this is safe).
- **CSP allows `'unsafe-eval'`** (Alpine) and inline style attributes; see `ARCHITECTURE.md` §8.
- **Static analysis (Larastan) not installed.** The package is hosted on GitHub, which this session's network policy blocks. Scheduled for Phase 12, or earlier in a session with access.
- **Pest not used**: Pest 5 needs PHP 8.4 and the development environment has 8.3; PHPUnit 12 is used throughout.
- **Pidgin strings are drafts** pending native-speaker review.
- **Provisional brand**: replace tokens when approved assets arrive.
- **Page weight**: workspace pages repeat inline SVG icons (about 130 KB of HTML before gzip on the dashboard). An SVG sprite is planned if Phase 7 low-bandwidth measurements show it matters.

## Phase 2 — Database and geographic management ☑

Completed 2026-10-24. Details: `docs/GEOGRAPHY_IMPORT.md`, `docs/DATA_MODEL.md`.

- ☑ Geography schema with PostGIS (generated `location` column, GiST index), pg_trgm name search, FK/unique/check constraints
- ☑ Triggers that maintain denormalised `lga_id`/`senatorial_district_id` and cascade moves
- ☑ Seeded 3 districts / 25 LGAs (idempotent), with no invented codes. SAMPLE wards/PUs via `SampleDataSeeder` (non-production only)
- ☑ Staged importer: private storage + hash, streaming CSV, row validation, in-file and cross-data duplicate detection, warnings, error report (formula-safe), preview counts, confirm/cancel, re-validation at apply, chunked transactions, single-apply lock, idempotent re-import, never-delete policy
- ☑ Queued jobs (`imports` queue on `redis-long`), verified end-to-end through Horizon
- ☑ `geography:import` CLI
- ☑ Tests: schema integrity, triggers, spatial queries, import lifecycle and rules (30 tests)

**Limitations.** No official ward/PU data has been loaded (none supplied). The import UI arrives with the
administration screens (Phase 6). Polling-unit moves between wards are deliberately manual.

## Phase 3 — Authentication, permissions and audit ☑

Completed 2026-10-24. Details: `docs/SECURITY.md`.

- ☑ Users converted to phone identity (encrypted number, keyed hash); dedicated `PHONE_HASH_KEY` / `FIELD_ENCRYPTION_KEY` (`campaign:keys`)
- ☑ Phone sign-in with one-time codes: hashed, expiring, attempt-capped, single-use, rate-limited, non-enumerating
- ☑ TOTP second factor (RFC 6238) enforced for privileged roles; QR enrolment; recovery codes
- ☑ PIN re-entry, idle lock, sensitive-area confirmation (`pin.confirm`)
- ☑ Sessions (device-aware database sessions) and devices: list, revoke, sign out everywhere; new-device SMS alerts
- ☑ Suspension/reactivation, administrator-assisted phone recovery, login history
- ☑ 12 roles / 61 permissions; scoped assignments; `GeoScope` + `visibleTo()`; anti-escalation rules
- ☑ Append-only, hash-chained audit log with triggers, `audit:verify`, `audit:checkpoint`; imports now audited
- ☑ SMS gateway contract with Africa's Talking and developer-outbox drivers; queued transactional SMS (`otp` queue)
- ☑ Workspace dashboard, account security, users and roles, audit log UI; permission-filtered navigation
- ☑ Tests: 190 passing (sign-in flow, non-enumeration, TOTP vectors, sessions, idle lock, PIN, scope isolation incl. URL manipulation and search, anti-escalation, audit tamper detection, crypto, SMS driver)
- ☑ Browser: real sign-in through the queue and outbox, 2FA enrolment, user creation, suspension, PIN, audit verification; desktop and 390 px, no overflow or JS errors

**Decisions.** Sanctum was not installed (GitHub downloads are blocked in this environment); the PWA and
API use same-origin session authentication, which Sanctum is not needed for. **Limitations.** See
`docs/SECURITY.md`.

## Phase 4 — Supporters and data protection ☑

Completed 2026-10-24. Details: `docs/DATA_PROTECTION.md` (draft NDPA assessment), `docs/DATA_MODEL.md`.

- ☑ Supporter registry: minimal fields, encrypted phone with keyed-hash dedupe, trigger-derived geography, 18+ attestation, sample flag
- ☑ Consent per channel and purpose, append-only (trigger), tied to privacy-notice versions; consent refused while no notice is published; only explicit grants recorded
- ☑ Suppression list keyed by hash (survives erasure); opt-outs lifted only by new consent; erasure only by the person's own new consent; objections only by the DPO
- ☑ Verification and rejection with history; audited corrections with optimistic locking; audited number reveal (permission + recent PIN)
- ☑ Follow-ups with encrypted notes ("asked not to be contacted" suppresses everything at once); trigram duplicate detection and merge
- ☑ Data-subject requests: reference, deadline, SMS acknowledgement, immediate effect for withdrawal, objection, restriction and erasure, identity verification, access package, completion and rejection
- ☑ Erasure by anonymisation (check constraint), legal hold, weekly retention job (`privacy:apply-retention`)
- ☑ Queued, scoped, masked, formula-safe exports; permission re-checked in the job; signed, requester-only, PIN-confirmed downloads; 24-hour expiry and pruning
- ☑ Settings service (audited) with `config/settings.php`
- ☑ UI: supporters list and record, exports, data-protection centre (requests, suppression, notices, retention), request page
- ☑ Tests: 232 passing; browser-verified registration, reveal, follow-up, correction, erasure request, suppression lookup, queued export and download, mobile layouts

**Limitations.** The privacy notice is a placeholder pending DPO and legal approval. Public self-service
(web registration with a one-time code, preferences) arrives in Phase 10; USSD and SMS in Phase 8.

## Phase 5 — Volunteers, canvassing and tasks ☑

Completed 2026-10-24. Details: `docs/DATA_MODEL.md` (field operations).

- ☑ Volunteer applications (deduplicated by phone hash), approval creating a ward-scoped account with the `volunteer` role and a welcome SMS, rejection, suspension (role revoked; account suspended and signed out only if it was the only role), reactivation, ward reassignment, skills and availability; full status history
- ☑ Training modules (Markdown, HTML stripped) with quizzes, pass marks, audiences, drafts and publishing; answers never sent to learners; best results per person
- ☑ Walk lists per ward with street/landmark stops and optional supporters not contacted in 14 days; assignment only to people who can canvass in the ward
- ☑ One-tap contact capture, idempotent on a device-generated UUID (ready for Phase 7 offline sync); future device times clamped; "asked not to be contacted" withdraws consent at once; repeat contacts within 60 minutes flagged; coordinator data-quality queue
- ☑ Tasks with area (statewide tasks visible only statewide), priority, due date, optimistic locking, comments, SHA-256-fingerprinted evidence on private storage with audited downloads, assignment within scope, manual escalation and full event history
- ☑ Hourly `tasks:process-overdue`: daily reminder to the assignee; escalation by priority grace period (urgent 2 h, high 12 h, normal 24 h, low 72 h) to managers of the ward, then LGA, district and state, skipping levels with nobody in post
- ☑ In-app notifications (database channel): top-bar bell polling once a minute, notification centre; only internal links followed
- ☑ Navigation items accept "any of" permissions; detail pages keep their section highlighted
- ☑ `SampleFieldOperationsSeeder`: SAMPLE coordinators, volunteers, training, a walk list and tasks
- ☑ Tests: 273 passing (scope isolation for every new list and detail page); browser-verified approval, training authoring view, task creation, assignment, comments, evidence upload, notification bell, mobile one-tap capture, task start and quiz

**Limitations.** Training content is SAMPLE only and must be replaced by campaign-approved material.
Notifications are in-app only (SMS/WhatsApp delivery of reminders arrives with the communications
engine in Phase 9). Contact capture needs a connection until the PWA sync in Phase 7.

## Phase 6 — Leadership and administration ☐

*Dependency:* install `laravel/reverb`.

- Role-specific dashboards and command centre (drill-down, coverage, approvals, health, trends)
- User management, approvals, import/export management, settings, content administration

## Phase 7 — PWA and offline synchronisation ☐

- Manifest, service worker, offline shell, IndexedDB queue with local encryption
- `/api/v1/sync` batch API with idempotency, partial failures and conflict handling
- Foreground sync fallback, retry controls, low-data mode

## Phase 8 — USSD and SMS ☐

- Africa's Talking adapter behind interfaces; Redis-backed USSD state machine
- Menus in English and Pidgin as specified; 182-character limit tests
- Inbound SMS: STOP, HELP and structured reports; browser USSD simulator

## Phase 9 — Communications engine ☐

- Templates and language variants; eligibility, consent and suppression at send time
- Scheduling, quiet hours, provider rate limits, retries, receipts, costs and caps
- Two-person approval for large sends; provider health

## Phase 10 — Public website and CMS ☐

- All 15 public pages, English/Pidgin, SEO, sitemap, social metadata, accessible forms, anti-spam
- CMS workflow with preview, scheduling, versions

## Phase 11 — Election-day operations ☐

- Agent assignment, check-ins, checklists, turnout observations, incidents
- Evidence storage with hashes; SMS fallback; result documents with arithmetic validation
- Discrepancy review, official-source comparison, evidence packs, command-centre views

## Phase 12 — Security, performance and launch readiness ☐

*Dependency:* install `larastan/larastan` for static analysis.

- k6 load tests against the brief's targets; security and accessibility reviews
- Backup/restore tests, monitoring, deployment hardening, runbook, pilot, launch assessment
