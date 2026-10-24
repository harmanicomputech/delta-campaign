# Architecture

This document records the architectural decisions for the Campaign Operations Platform. Every phase
must conform to it; changes are made here first, with a short rationale.

## 1. Shape of the system

A single **Laravel 13 modular monolith** serves every surface:

```
                         ┌───────────────────────────────────────────────┐
  Browser (public)  ───► │  Public website   (Blade, server-rendered)    │
  Browser (staff)   ───► │  Workspaces       (Blade + Livewire + Alpine) │
  PWA (field)       ───► │  PWA shell + /api/v1/sync (Sanctum/session)   │
  Africa's Talking  ───► │  /webhooks/ussd, /webhooks/sms, receipts      │
                         ├───────────────────────────────────────────────┤
                         │  Domain services (app/Services/<Domain>)      │
                         │  Policies + geographic scope                  │
                         │  Events / Listeners / Jobs                    │
                         ├──────────────┬──────────────┬─────────────────┤
                         │ PostgreSQL + │    Redis     │  S3-compatible  │
                         │   PostGIS    │ cache/queue/ │  object storage │
                         │              │ USSD state   │  (private)      │
                         └──────────────┴──────────────┴─────────────────┘
                                 ▲               ▲
                       Horizon queue workers   Scheduler   Reverb (real-time, Phase 6+)
```

There is no separate Node backend, SPA or native-app project. Node is used only at build time (Vite).

## 2. Technology decisions

| Concern | Decision | Notes |
| --- | --- | --- |
| Runtime | PHP ≥ 8.3 (Docker image: 8.4), `declare(strict_types=1)` in all app code | |
| Framework | Laravel 13 | |
| UI | Blade components + Livewire 4 + Alpine.js (bundled with Livewire) + Tailwind CSS 4 + Vite 8 | |
| Admin framework | **No Filament.** All workspaces use the in-house component system | Avoids a generic admin aesthetic; one design language for every surface |
| Database | PostgreSQL 16 + PostGIS 3.4 | `geography(Point,4326)` for locations |
| Cache / queues / sessions | Redis for cache, queues, rate limits and USSD state; **database** session driver | DB sessions let users list and revoke their own sessions (Phase 3) |
| Queues | Laravel Horizon, separate supervisors for `ussd`, `messaging`, `sync`, `imports`, `default` | |
| Real-time | Laravel Reverb (command centre, election day) | Added in Phase 6 |
| API auth | Session cookies for same-origin PWA; Sanctum tokens only for device-bound sync where needed | |
| Tests | PHPUnit 12 on a PostgreSQL test database; Playwright browser checks | Pest 5 needs PHP 8.4 (see audit) |
| Icons | Lucide (ISC licence), copied as SVG files into `resources/icons` | Rendered by `<x-icon>` |

## 3. Code organisation

Laravel conventions first; domain organisation where it pays off.

```
app/
  Actions/<Domain>/           single-purpose write operations (e.g. RegisterSupporter)
  Enums/                      backed enums for every controlled status value
  Http/Controllers/{Public,Workspace,Api/V1,Webhooks}/
  Http/Middleware/            SecurityHeaders, AssignRequestId, SetLocale, …
  Http/Requests/              Form Requests: validation + authorisation
  Livewire/<Area>/            Livewire components
  Models/                     Eloquent models (flat, Laravel convention)
  Policies/                   one per scoped model
  Services/<Domain>/          domain services, provider adapters, interfaces
  Support/                    small cross-cutting helpers (navigation, logging)
resources/views/
  components/ui/              design-system primitives (<x-ui.button>, …)
  components/layouts/         shells (app, auth, public)
  livewire/  pages/  public/  errors/
```

Rules:

- Controllers and Livewire components stay thin; business rules live in Actions/Services.
- Every write that changes more than one table runs in a DB transaction.
- Provider-specific code (Africa's Talking, WhatsApp, S3) sits behind an interface in `Services/<Domain>/Contracts`.
- No class is created before it has a purpose; no empty directories.

## 4. Identity, roles and geographic scope

- `users` are identified by phone number (E.164). Phone numbers are stored **encrypted**
  (`phone_encrypted`) with a **keyed hash** (`phone_hash = HMAC-SHA256(e164, PHONE_HASH_KEY)`) for
  uniqueness and look-ups. The plain number is never logged.
- OTPs and PINs are stored only as hashes, with attempt counters, expiry and rate limits. Public
  responses never disclose whether a number is registered.
- Privileged roles require a second factor (TOTP) before reaching sensitive areas.
- **RBAC**: `roles`, `permissions`, `role_permission`, and `role_assignments(user_id, role_id,
  scope_type, scope_id)`. A user may hold several scoped assignments.
- **Geographic scope** is resolved once per request into a `GeoScope` value object (statewide, or
  sets of district/LGA/ward IDs). Scoped tables carry denormalised, indexed `senatorial_district_id`,
  `lga_id`, `ward_id` columns so that scope filters are single indexed predicates.
  `Model::query()->visibleTo($user)` applies the scope; policies re-check single records. Jobs carry
  the initiating user's ID and re-resolve scope when they run.

## 5. Data protection by design

- Collect only what is required; NIN, BVN, PVC/VIN, religion and ethnicity are never collected.
- Consent is recorded per channel (`sms`, `whatsapp`, `voice`, `email`) with the notice version and
  source; withdrawal is a new record, never an overwrite.
- A suppression list is checked immediately before every send, including already-queued messages.
- Sensitive fields use Laravel encrypted casts with a dedicated key (`FIELD_ENCRYPTION_KEY`), kept
  separate from `APP_KEY`.
- Evidence and exports live on a **private** disk, accessed only through short-lived signed URLs.
- The **audit log** is append-only: each row stores `hash = SHA-256(previous_hash ‖ canonical
  payload)`. PostgreSQL triggers reject `UPDATE`/`DELETE`; `php artisan audit:verify` re-walks the
  chain.
- The log processor redacts phone numbers, OTPs, PINs, tokens and passwords before writing.

## 6. Key domain designs (summary; detailed in each phase)

| Domain | Design notes |
| --- | --- |
| Geography | `senatorial_districts → lgas → wards → polling_units`; codes unique per parent; staged imports with preview, row errors and idempotent re-import |
| Supporters | Deduplicated on `phone_hash`; `source` enum; verification status; consent history; retention dates |
| Field ops | Volunteers, walk lists, contact attempts, tasks with optimistic `version` columns |
| Offline sync | Client generates UUIDs per operation; `/api/v1/sync` accepts batches, returns a per-operation result; `sync_operations` gives idempotency; `version` conflicts return the server copy |
| USSD | Stateless HTTP callback; session state in Redis (TTL); menu screens are classes in a small state machine; every screen ≤ 182 characters (enforced by tests); slow work dispatched to queues |
| Messaging | Templates with language variants → campaigns → per-recipient messages; eligibility and suppression checked at send time; two-person approval above a threshold; spend caps; delivery receipts |
| Election day | Three separate concepts that are never mixed: **submitted** (agent reports), **verified** (campaign review) and **official** (from INEC sources). Original evidence keeps its SHA-256 hash |
| Content | Draft → review → approved → scheduled → published → withdrawn/archived, with versions and language variants |

## 7. Design system

The visual language is defined once, as **design tokens** in `resources/css/app.css` (Tailwind 4
`@theme`), and consumed by Blade components in `resources/views/components/ui`.

- **Provisional palette.** No approved brand assets were supplied. The primary colour is a deep
  "Delta green" and the accent a restrained gold, both clearly provisional. Changing the brand means
  editing the `--color-brand-*` / `--color-accent-*` tokens only.
- **Typography**: Inter (UI, tabular numerals for data) and Fraunces (display headings on the public
  site), self-hosted via Fontsource. No third-party font CDN.
- **Themes**: light and dark, plus "system", applied with a `data-theme` attribute before first paint.
- **Density**: comfortable by default; tables offer a compact density.
- **Accessibility**: WCAG 2.2 AA contrast, visible focus rings, 44 px minimum touch targets on
  mobile, reduced-motion support, labelled controls, accessible dialogs (focus trap, `Esc`, return
  focus).
- **No logo is invented.** The shell shows a neutral monogram and the configurable `APP_NAME`.

Navigation is defined centrally in `config/navigation.php`. Each item declares the permission it
needs and the phase that delivers it; items for modules not yet built render as disabled
"Planned" entries, never as dead links.

## 8. Observability and operations

- Every request gets an `X-Request-Id`, added to log context and returned in the response.
- Production logs are JSON (stdout) for collection by the hosting platform.
- `GET /up` (liveness) and `GET /health` (readiness: database, PostGIS, Redis, cache, queue), with
  appropriate HTTP status codes.
- Security headers are applied by middleware (CSP, HSTS in production, frame, referrer and
  permissions policies). The CSP is nonce-based (a fresh nonce per request), with two documented
  relaxations: `'unsafe-eval'` in `script-src`, which Alpine's standard build needs, and
  `style-src-attr 'unsafe-inline'`, for style attributes set by Livewire morphing and transitions.
  Livewire's CSP-safe build, which would remove `'unsafe-eval'`, is to be evaluated in Phase 12.
  Horizon's dashboard is excluded from the CSP because it ships its own inline assets.
- CI: Pint, PHPUnit on PostgreSQL+PostGIS and Redis services, Vite build, `composer audit`,
  `npm audit`.

## 9. Environments

`local` → `staging` → `production`, configured entirely by environment variables
(`.env.example` is the reference). Production refuses to boot with `APP_DEBUG=true` or a missing
`PHONE_HASH_KEY` / `FIELD_ENCRYPTION_KEY` (enforced from Phase 3, when the keys are first used).
