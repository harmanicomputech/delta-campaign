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

## Phase 6 — Leadership and administration ☑

Completed 2026-10-24.

- ☑ Command centre (`command-centre.view`): area KPIs (supporters, verification, active volunteers, 7-day field contacts and reach, ward coverage), 30-day daily trends as two single-series charts with hover values and table view, drill-down district → LGA → ward with "up", escalations, decisions waiting and system health. Tampered drill parameters outside the viewer's scope are ignored
- ☑ `AreaMetrics`: aggregate-only, scope-restricted counts, cached for 60 s; the summary is always the sum of the breakdown. Small numbers (1–4) are shown as "<5" to aggregate-only roles (e.g. analysts)
- ☑ Approvals inbox (`approvals.view`): volunteer applications, supporters awaiting verification, contacts to review, escalated tasks, data-subject requests and geography imports, each limited to the person's permission and area
- ☑ Role-aware dashboard: my tasks, walk lists and training for field roles; decisions waiting and an area snapshot for leaders
- ☑ Settings (`settings.manage`, recent PIN): validated against `config/settings.php`, audited, with change history
- ☑ Geography browser (`geography.view`, scope-limited) with polling-unit search; imports UI (`geography.import`): upload starts validation, preview with row filters and issue report, confirm (recent PIN) or cancel, CSV template, background apply with live status
- ☑ Tests: 284 passing; browser-verified dashboard, command centre drill-down and chart hover, approvals, geography, import upload → preview → confirm → applied, mobile layouts

**Deviation.** `laravel/reverb` cannot be installed in this environment (GitHub downloads blocked), so
live updates use Livewire polling (60 s on the command centre and approvals, 3 s on running imports),
matching the metrics cache. Reverb can replace polling later without changing the screens.
Content administration arrives with the CMS in Phase 10.

## Phase 7 — PWA and offline synchronisation ☑

Completed 2026-10-24. Security model: `docs/SECURITY.md` (offline field app).

- ☑ Installable app: manifest (standalone, maskable icon, shortcuts), icons generated from the existing neutral mark
- ☑ Service worker (`/sw.js`, versioned from the Vite manifest): precaches the offline shell and build assets; pages are network-only with the offline shell as fallback; nothing personal is cached
- ☑ Encrypted IndexedDB vault (AES-GCM, non-extractable device key) for assigned walk lists and the capture queue
- ☑ One-tap capture works offline on the walk-list page and from the offline shell; "Saved on this device" feedback
- ☑ `POST /api/v1/sync`: batches of up to 100, per-item results (`created`, `duplicate`, `conflict`, `rejected`, `error`), idempotent on client UUIDs; `GET /api/v1/sync/walk-lists` for the device cache
- ☑ Sync triggers: on reconnect, on page load, every minute while items wait, and "Sync now"; items needing attention can be retried or discarded; sign-in/unlock prompts when the session ended
- ☑ Low-data mode (follows the browser's Save-Data hint; per device) stops background polling
- ☑ Tests: 294 passing; browser-verified install metadata, service-worker control, offline capture, offline shell, encrypted storage, automatic sync on reconnect

**Limitations.** Offline capture covers canvassing contacts; other forms need a connection. Background
Sync (service-worker sync events) is not relied on because support varies; sync runs whenever the app is open.

## Phase 8 — USSD and SMS ☑

Completed 2026-10-25. Details: `docs/USSD_AND_SMS.md`.

- ☑ `UssdProvider` interface with the Africa's Talking adapter; secret-protected, rate-limited webhooks outside the web stack
- ☑ USSD state machine (encrypted cache state, newest-input processing, retry idempotency, expiry) with the specified menu in English and Pidgin: join, volunteer, polling unit (code or browse), projects, callback, preferences/deletion
- ☑ Pagination, back, home, validation and friendly errors; every screen and closing message ≤ 182 characters, tested with worst-case data in both languages, plus a runtime safety net
- ☑ Callback requests as LGA tasks (one per number per day) with an audited number reveal on the task page
- ☑ Inbound SMS: STOP / START / HELP and the new `REPORT <code> <message>` format for staff (scope-checked, becomes a task); hashed sender, encrypted text, queue processing, provider-id de-duplication
- ☑ Browser USSD simulator running the real menu, with practice mode (rolled back)
- ☑ Tests: 322 passing; measured callback latency (median 10 ms); browser-verified simulator

## Phase 9 — Communications engine ☑

Completed 2026-10-25. Details: `docs/MESSAGING.md`.

- ☑ Templates (SMS / WhatsApp, English and Pidgin, whitelisted placeholders, opt-out wording required for SMS, approved provider template required for WhatsApp); segment counting (GSM-7 / Unicode)
- ☑ Campaigns with audiences limited to the creator's area; live estimate of eligible recipients, segments and cost
- ☑ Independent approval above a configurable threshold, scope-checked; creator and approver recorded, audited and notified; approvals inbox queue
- ☑ Queue-based sending that re-checks consent, suppression, record state, quiet hours, budget, provider health and rate limit for every message immediately before sending
- ☑ Retries with backoff (max 3), no retries for permanent failures, provider circuit breaker, pause/resume/cancel, scheduling (`messaging:tick` every minute)
- ☑ SMS delivery reports and WhatsApp Cloud API (template sends, signed status webhooks, STOP replies)
- ☑ Monthly spending limit and cost tracking; provider health tab
- ☑ Tests: 337 passing; browser-verified templates, editor estimate, approval and send via Horizon, campaign results

## Phase 10 — Public website and CMS ☑

Completed 2026-10-25. Details: `docs/PUBLIC_SITE.md`.

- ☑ Public pages: home, about, manifesto, projects (list and record), LGAs (list and page), news, fact-checks, events (list, page, ticket), register, volunteer, polling-unit lookup, contact, privacy notice, message preferences; English and Pidgin (Pidgin draft pending native review)
- ☑ SEO: per-page title, description, canonical URL, Open Graph / Twitter cards, JSON-LD, dynamic sitemap, RSS feed for news; robots.txt blocks everything outside production
- ☑ Forms (Livewire): one-time-code phone verification, honeypot, minimum fill time, per-IP rate limits; no third-party captcha or trackers
- ☑ CMS (pages, news, fact-checks): draft → review by someone other than the author → approve → publish now or schedule (`content:publish-due` every minute) → withdraw / archive; versions with restore; the live site serves the approved snapshot while edits are reviewed
- ☑ Media: images re-encoded to WebP at three widths (removes EXIF/location data), alt text in both languages
- ☑ Project directory: every record needs a source and a verification status; two-person approval; edits take a record off the site until re-approved
- ☑ Events: area-limited organisers, capacity with locking, SMS ticket with an opaque QR token, door check-in (GET never changes data), walk-ins, cancellation SMS to registrants
- ☑ Rapid response: cases with priority and deadline, assignment, notes, hashed private evidence (audited downloads), verdict and finding, optional legal review, approval (not by the creator) that only creates a draft fact-check for the normal CMS review
- ☑ Contact inbox (`contact.manage`, new permission): encrypted messages, every opening audited, handled / spam states
- ☑ Approvals inbox: content, project and rapid-response queues (reviewers never see their own work)
- ☑ SAMPLE seeder: About/Manifesto stay unpublished placeholders; sample news, fact-check, project, event, case and message are flagged SAMPLE
- ☑ Tests: 385 passing; browser-verified home, project directory, event registration with OTP → ticket → staff check-in (and repeat scan), contact form → inbox, CMS self-approval block, rapid-response workflow, mobile layouts (no horizontal overflow)

**Changes.** The super-administrator exemption from content self-approval was removed: no one approves
their own content, project records, rapid-response cases or message campaigns.

## Phase 11 — Election-day operations ☑

Completed 2026-10-25. Details: `docs/ELECTION_DAY.md`.

- ☑ Election setup (`election.setup`, new permission): parties copied from the official ballot, configurable opening checklist and turnout times, one active election at a time
- ☑ Polling-agent assignment by coordinators in their area (primary/backup); grants and revokes a polling-unit-scoped role; SMS on assignment
- ☑ Agent app: arrival with optional location and distance check, opening checklist, turnout observations, incidents with photos, result sheet with live arithmetic hints and a required photo; idempotent client UUIDs
- ☑ Structured SMS fallback: `ARRIVED`, `TURNOUT`, `INCIDENT`, `RESULT`, agent `HELP`; retry-safe
- ☑ Incident management: severity-ordered queue, acknowledge / escalate / resolve / dismiss / reopen in area, alerts for high and critical incidents
- ☑ Evidence: originals kept unchanged with SHA-256, immutable records, audited access, daily integrity check (`evidence:verify`)
- ☑ Results: arithmetic validation, conflict detection, verification by a second person (photo required), official figures only from cited INEC sources, field-by-field comparison and discrepancy list
- ☑ Evidence packs: ZIP with CSVs, originals, hash manifest and "not official" notice; signed, PIN-protected, audited, 24-hour expiry
- ☑ Reporting: election-day hub (coverage, incidents, verified vs official tallies, discrepancies), command-centre strip, dashboard card for agents; all scope-limited
- ☑ SAMPLE training election with placeholder parties (SPA/SPB/SPC) and illustrative reports
- ☑ Tests: 413 passing; browser-verified agent flow on a phone (arrival with location, checklist, turnout, incident with photo, result with live arithmetic warning), incident triage, verification, official figures and discrepancy, evidence pack build, mobile layouts

**Fixes.** Time-dependent messaging tests (Phase 9) travelled to "today 12:00", which falls before the
privacy notice is published once the real time is past noon; they now use the next day.

**Limitations.** Offline election reporting uses the SMS fallback rather than the PWA queue.

## Phase 12 — Security, performance and launch readiness ☐

*Dependency:* install `larastan/larastan` for static analysis.

- k6 load tests against the brief's targets; security and accessibility reviews
- Backup/restore tests, monitoring, deployment hardening, runbook, pilot, launch assessment
