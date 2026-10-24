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

## Phase 1 — Laravel foundations and premium design system ◐

- Laravel 13 configured for PostgreSQL + PostGIS, Redis cache/queue, database sessions
- Horizon installed with domain queue supervisors
- Docker: `Dockerfile` (PHP 8.4-FPM + Nginx), `compose.yaml` (app, worker, scheduler, PostGIS, Redis)
- Vite + Tailwind 4 design tokens, light/dark themes, self-hosted fonts, icon component
- Blade + Livewire + Alpine component library (buttons, inputs, selects, tables, badges, alerts,
  modals, slide-overs, dropdowns, tabs, toasts, empty/skeleton states, page header, breadcrumbs,
  command palette)
- Application shell: collapsible sidebar, mobile drawer + bottom bar, top bar, breadcrumbs
- Authentication screen designs (phone entry, OTP) — functional in Phase 3
- Dashboard shell with real system-health data
- Security headers, request IDs, redacting/structured logs, branded error pages
- English and Nigerian Pidgin (`pcm`) locale switching for shell strings
- GitHub Actions CI; initial feature tests
- `.env.example`, `README.md`

## Phase 2 — Database and geographic management ☐

- Geography schema (districts, LGAs, wards, polling units) with PostGIS columns, constraints, indexes
- Seed 3 districts and 25 LGAs from the brief; **no fabricated** ward/PU codes
- Staged CSV importer (queued): preview, row validation, duplicate detection, error report,
  import history, idempotent re-import
- Geography browser UI; tests for import and re-import

## Phase 3 — Authentication, permissions and audit ☐

*Dependency:* install `laravel/sanctum` (blocked by this session's network policy; see audit).

- Phone OTP sign-in (hashed OTPs, expiry, attempts, rate limits, non-enumerating responses)
- TOTP second factor for privileged roles; PIN re-entry
- Roles, permissions, scoped role assignments, `GeoScope` + `visibleTo()`
- Session list/revocation, device registry, suspension, recovery, login history, suspicious-login alerts
- Append-only, hash-chained audit log with DB triggers and `audit:verify`
- Security and geographic-isolation tests

## Phase 4 — Supporters and data protection ☐

- Supporter registration, phone verification, source tracking, dedupe on keyed hash
- Channel-specific consent with notice versions; withdrawal; suppression list
- Verification, corrections, follow-ups, scoped exports (queued, audited)
- Data-subject requests (access, rectification, erasure) workflow for the DPO; retention jobs
- NDPA compliance assessment draft (`docs/DATA_PROTECTION.md`) for legal review

## Phase 5 — Volunteers, canvassing and tasks ☐

- Volunteer applications, approvals, assignments, skills/availability, training and quizzes
- Walk lists, contact attempts and outcomes, follow-ups, data-quality review
- Tasks: priority, due dates, comments, evidence, reassignment, escalation, overdue reminders
- Notifications

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
