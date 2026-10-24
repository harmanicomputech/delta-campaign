# Deployment

> Phase 1 establishes the image and stack. Hardening, backups, restore tests, monitoring and the
> operational runbook are delivered in Phase 12.

## Image

`Dockerfile` builds one image (PHP 8.4-FPM, Alpine) that runs in one of three roles, selected by
`ROLE`:

| Role | Process | Notes |
| --- | --- | --- |
| `web` | nginx + php-fpm (supervisord), port 8080 | Health check: `GET /up` |
| `horizon` | `php artisan horizon` | Queue supervisors in `config/horizon.php` |
| `scheduler` | `php artisan schedule:work` | Run exactly one replica |

Build stages: Composer dependencies (no dev) → Vite assets (needs `vendor/livewire`) → runtime.
The entrypoint caches config, routes, views and events on start (except when `APP_ENV=local`).

> **Not verified locally.** The development environment used for Phase 1 had no Docker daemon.
> The image is built by the `docker` job in CI; treat the first green CI run as its verification.

## Environments

| | local | staging | production |
| --- | --- | --- | --- |
| `APP_DEBUG` | true | false | false (boot fails if true) |
| `LOG_CHANNEL` | stack/daily | json | json |
| `SESSION_SECURE_COOKIE` | false | true | true |
| `SECURITY_HSTS_ENABLED` | false | true | true |
| `DB_SSLMODE` | prefer | require | require |
| `DESIGN_SYSTEM_ENABLED` | true | optional | ignored (never served) |

Set `TRUSTED_PROXIES` to the load balancer's addresses so client IPs (used by rate limiting) and HTTPS
detection are correct.

## Database

- PostgreSQL 16 with PostGIS 3.4. The migration `enable_postgis` runs `CREATE EXTENSION IF NOT EXISTS
  postgis`. The migrating role needs permission to create extensions. On managed services, either
  create the extension once as the provider's admin role or grant the app role that right.
- The PostGIS migration's `down()` deliberately does nothing: dropping the extension would destroy
  geography columns.

## Release procedure (initial)

1. CI green on `main` (style, tests, audits, image build).
2. Build and push the image tagged with the commit SHA.
3. **Back up the database** (procedure and restore test: Phase 12).
4. Run migrations once, as a one-off task: `php artisan migrate --force`.
5. Roll out `web`, then `horizon` (`php artisan horizon:terminate` lets workers finish), then `scheduler`.
6. Confirm `GET /health` returns `"status":"ok"`.

## Rollback

- Redeploy the previous image tag.
- Only roll back migrations if the release notes state they are reversible and no data has been written
  that depends on them. Otherwise restore from the pre-release backup. Every migration from Phase 2
  onward will document its rollback safety.

## Health endpoints

| Endpoint | Purpose | Response |
| --- | --- | --- |
| `GET /up` | Liveness (framework boots) | 200 |
| `GET /health` | Readiness: database, PostGIS, Redis, cache, Horizon | 200 `ok`/`degraded`, 503 `failed`; JSON, no internal details, no session |
