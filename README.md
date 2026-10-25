# Campaign Operations Platform

A single Laravel application serving the campaign's public website, staff workspaces, leadership
command centre, installable PWA, USSD/SMS endpoints and internal APIs.

> **Status:** Phase 1 (foundations and design system) complete. See
> [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) for what is built and what comes next.

| Document | Purpose |
| --- | --- |
| [`docs/PROJECT_AUDIT.md`](docs/PROJECT_AUDIT.md) | Starting-point audit, risks, information still to be supplied |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Architecture decisions every phase follows |
| [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) | Phased plan, status and known limitations |
| [`docs/DESIGN_SYSTEM.md`](docs/DESIGN_SYSTEM.md) | Tokens, components and UI conventions |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Docker image, environments, release and rollback |

## Stack

Laravel 13 · PHP ≥ 8.3 · Livewire 4 + Alpine.js · Tailwind CSS 4 · Vite 8 · PostgreSQL 16 + PostGIS 3.4 ·
Redis 7 · Laravel Horizon · PHPUnit 12 · Docker · GitHub Actions

## Local development

Requirements: PHP 8.3+ (extensions `pdo_pgsql`, `redis`, `intl`, `pcntl`, `zip`, `bcmath`), Composer 2,
Node 22, PostgreSQL 16 with PostGIS 3.4, Redis 7.

```bash
composer install
npm ci
cp .env.example .env            # then set DB_PASSWORD etc.
php artisan key:generate

createdb campaign && createdb campaign_test   # as a role allowed to CREATE EXTENSION postgis
php artisan migrate

npm run build                   # or `npm run dev` for hot reload
php artisan serve               # http://localhost:8000
php artisan horizon             # queue workers (separate terminal)
```

Useful pages in local/staging (never served in production):

| URL | What it is |
| --- | --- |
| `/` | Public website |
| `/login` | Staff sign-in (phone + one-time code; codes appear in the developer outbox) |
| `/workspace` | Staff workspace |
| `/design-system` | Component and token reference with live examples |
| `/design-system/outbox` | Developer SMS outbox (sign-in codes and all outgoing SMS when `SMS_DRIVER=outbox`) |
| `/health` | JSON readiness probe (database, PostGIS, Redis, cache, Horizon, scheduler, SMS) |
| `/up` | Liveness probe |
| `/horizon` | Horizon dashboard (`system.horizon` permission) |

Sample data for trying everything (never in production):

```bash
php artisan db:seed --class=SampleDataSeeder
php artisan campaign:create-admin 08030000001 "Your Name"
php artisan schedule:work       # scheduled jobs (separate terminal)
```

### Docker

```bash
cp .env.example .env   # set DB_PASSWORD and APP_KEY
docker compose up --build
docker compose exec web php artisan migrate --force
```

The stack runs `web` (nginx + php-fpm), `horizon`, `scheduler`, `postgres` (PostGIS) and `redis`.
See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Quality checks

```bash
php artisan test          # PHPUnit on PostgreSQL (database: campaign_test)
vendor/bin/pint --test    # code style
composer audit && npm audit
php artisan campaign:launch-check            # configuration, content, data and health before go-live
node tests/Browser/accessibility.cjs         # axe-core WCAG 2.1 AA scan (needs a dev server with sample data)
```

Load tests (k6, for staging) are in `tests/Load/`; measured results are in
[`docs/LOAD_TESTING.md`](docs/LOAD_TESTING.md).

CI (`.github/workflows/ci.yml`) runs style, tests on PHP 8.3 and 8.4 against PostGIS and Redis services,
dependency audits and a Docker image build.

## Configuration

Every environment variable is documented in [`.env.example`](.env.example). Variables for later phases
(Africa's Talking, WhatsApp, Reverb, data-protection keys) are listed there commented out.

## Operations

- [`docs/RUNBOOK.md`](docs/RUNBOOK.md) — day-to-day operations, incidents, backups and restores, election day
- [`docs/LAUNCH_READINESS.md`](docs/LAUNCH_READINESS.md) — what is done, what blocks launch, the pilot plan
- [`docs/SECURITY_REVIEW.md`](docs/SECURITY_REVIEW.md) — Phase 12 security and accessibility review

## Content and data rules

- No candidate biography, achievements, projects, results, polling-unit codes or logos are invented.
  Unsupplied content is marked `[PLACEHOLDER: …]`; synthetic records and figures are marked **SAMPLE**.
- NIN, BVN, PVC/VIN, religion and ethnicity are never collected.
- Pidgin (`pcm`) strings are drafts pending native-speaker review.
