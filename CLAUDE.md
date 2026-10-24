# Campaign Operations Platform — agent guidance

Laravel 13 modular monolith (Blade + Livewire 4 + Alpine + Tailwind 4, PostgreSQL 16 + PostGIS, Redis, Horizon).
Read `docs/ARCHITECTURE.md` before changing structure and `docs/IMPLEMENTATION_PLAN.md` for the current phase.

## Rules

- Work one phase at a time; stop for review at the end of each phase.
- `declare(strict_types=1);` in all PHP files under `app/`, `config/` excluded.
- Business rules go in `app/Actions` / `app/Services/<Domain>`; controllers and Livewire components stay thin.
- Controlled values are backed enums in `app/Enums`. Schema changes only via migrations.
- UI uses the components in `resources/views/components/ui`; tokens live in `resources/css/app.css`. Do not hard-code brand colours.
- Never invent candidate facts, projects, results, polling-unit codes or logos. Use `[PLACEHOLDER: …]` or mark records `SAMPLE`.
- Never log phone numbers, OTPs, PINs or tokens in plaintext.
- No dead buttons or links: unbuilt navigation items are declared "planned" in `config/navigation.php`.

## Commands

- Tests: `php artisan test` (PostgreSQL database `campaign_test`, see `phpunit.xml`)
- Style: `vendor/bin/pint` (CI runs `vendor/bin/pint --test`)
- Assets: `npm run build` / `npm run dev`
- Icons: add the name to `scripts/icons.json`, then `npm run icons`
