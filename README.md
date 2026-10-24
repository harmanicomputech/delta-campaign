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
| `/` | Public holding page (full public site: Phase 10) |
| `/design-system` | Component and token reference with live examples |
| `/design-system/shell` | Workspace dashboard shell with live system status |
| `/design-system/auth/sign-in` | Sign-in screen design (functional in Phase 3) |
| `/health` | JSON readiness probe (database, PostGIS, Redis, cache, queue) |
| `/up` | Liveness probe |
| `/ops/horizon` | Horizon dashboard (local only until Phase 3 permissions) |

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
```

CI (`.github/workflows/ci.yml`) runs style, tests on PHP 8.3 and 8.4 against PostGIS and Redis services,
dependency audits and a Docker image build.

## Configuration

Every environment variable is documented in [`.env.example`](.env.example). Variables for later phases
(Africa's Talking, WhatsApp, Reverb, data-protection keys) are listed there commented out.

## Content and data rules

- No candidate biography, achievements, projects, results, polling-unit codes or logos are invented.
  Unsupplied content is marked `[PLACEHOLDER: …]`; synthetic records and figures are marked **SAMPLE**.
- NIN, BVN, PVC/VIN, religion and ethnicity are never collected.
- Pidgin (`pcm`) strings are drafts pending native-speaker review.
