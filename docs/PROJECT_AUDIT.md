# Project Audit — Phase 0

**Date:** 2026-10-24
**Repository:** `harmanicomputech/delta-campaign`
**Auditor role:** Principal architect (Claude Code session)

## 1. Summary

The supplied repository (`harmanicomputech/delta-campaign`) was **empty** at the time of the audit:
no commits, branches, source code, database dumps, environment files, assets or deployment
configuration. This is a greenfield build.

Consequently there is:

- **No existing functionality to preserve**, and no destructive migration risk.
- **No existing data** (supporters, volunteers, geography) to migrate or protect.
- **No credentials** in the repository to rotate or redact.
- **No brand assets** (logo, colours, typography, photography) supplied.
- **No candidate biography, project records or manifesto content** supplied.
- **No official geographic reference data** (wards, polling units) supplied.

The project was bootstrapped from the official `laravel/laravel` skeleton (Laravel 13.38) and
connected to the repository as its first commit.

## 2. Inspection checklist

| Area | Finding | Action |
| --- | --- | --- |
| Framework / architecture | None present | Laravel 13 modular monolith (see `ARCHITECTURE.md`) |
| Database structure / data | None present | PostgreSQL 16 + PostGIS 3.4 schema designed from the brief |
| Authentication / authorisation | None present | Phone OTP, privileged 2FA, RBAC + geographic scope (Phase 3) |
| Frontend / design components | None present | Custom Blade + Livewire + Alpine + Tailwind 4 design system (Phase 1) |
| Payment integrations | None present, and none required by the brief | Not planned; would require an approved requirement |
| Messaging / notification integrations | None present | Provider-abstracted SMS/WhatsApp engine (Phases 8–9) |
| Africa's Talking configuration | None present | Adapter behind `UssdGateway` / `SmsGateway` interfaces (Phase 8) |
| API endpoints | None present | Versioned `/api/v1` with OpenAPI docs (incrementally) |
| Environment variables | Skeleton defaults only | Documented in `.env.example` |
| Deployment arrangements | None present | Docker + GitHub Actions (Phase 1, hardened in Phase 12) |
| Reusable functionality | Only the Laravel skeleton | — |
| Dependencies to migrate/remove | None. Skeleton CI workflows for the upstream `laravel/laravel` repository were removed as irrelevant | — |
| Security vulnerabilities | None in code (nothing present). `composer audit` reports no advisories for installed packages | Continuous `composer audit` / `npm audit` in CI |
| Data migration / compatibility risks | None | — |

## 3. Development environment findings

These affect how the work in this session is carried out and verified. They are not defects in the product.

| Finding | Impact | Mitigation |
| --- | --- | --- |
| Local PHP is **8.3.6**; current Pest 5 requires PHP 8.4 | Pest 5 cannot run locally | Use **PHPUnit 12.5** (the brief allows Pest or PHPUnit). The codebase targets PHP ≥ 8.3; the Docker image uses PHP 8.4 |
| The session's network proxy limits GitHub downloads to repositories attached to the session | Composer packages hosted on GitHub that are not already cached (e.g. Sanctum, Reverb, Larastan, Filament) cannot be installed in this session; Packagist metadata and npm work | Install them in a session with broader GitHub access, or in CI. Recorded in the implementation plan as a dependency for Phases 3, 6 and 12 |
| Docker daemon is not running in this environment | `compose.yaml` and `Dockerfile` cannot be built or run here | Verified natively with PostgreSQL 16 + PostGIS 3.4 and Redis 7. Docker files are written to match and will be verified by CI or locally |
| PostGIS was not installed by default | — | Installed `postgresql-16-postgis-3` for local verification |

## 4. Information that must be supplied (not invented)

The platform will use clearly marked placeholders (`[PLACEHOLDER: …]`) or `SAMPLE` records until
the campaign provides:

1. **Brand assets**: approved logo files, colour palette, typography and photography. A provisional,
   centrally editable design-token system is used meanwhile (see `ARCHITECTURE.md` §7).
2. **Candidate information**: biography, portrait, approved manifesto/campaign plan text.
3. **Project directory records**: titles, locations, dates and source notes for public projects.
4. **Geographic reference data**: official ward and polling-unit lists (e.g. from INEC publications).
   The 25 Delta State LGAs and three senatorial districts are seeded from the brief; no polling-unit
   codes, boundaries or voter counts will be fabricated.
5. **Africa's Talking account**: username, API key, USSD service code and SMS sender ID/short code.
6. **WhatsApp Business Platform**: phone number ID, access token and approved message templates.
7. **Data protection**: name and contact of the Data Protection Officer, lawful-basis assessment and
   approved privacy-notice text. A draft compliance assessment will be produced for legal review.
8. **Hosting decision**: cloud provider/region, domain names and S3-compatible storage provider.
9. **Pidgin translations**: a native-speaker reviewer for all Pidgin content. Machine-drafted Pidgin
   strings are marked for review.

## 5. Principal risks

| # | Risk | Severity | Mitigation |
| --- | --- | --- | --- |
| R1 | Processing political-opinion data (a special category under NDPA 2023) | High | Data minimisation, explicit channel-specific consent, DPO workflow, field-level encryption, access audit; legal review before launch |
| R2 | Geographic scope leakage (e.g. a ward coordinator reading another ward) | High | Scope enforced in query layer + policies, tested on records, searches, exports, APIs and jobs |
| R3 | Election-day load spikes and network unreliability | High | Queue-first ingestion, idempotent client UUIDs, SMS fallback, k6 load tests, Redis-backed hot paths |
| R4 | Misrepresenting campaign-collected tallies as official results | High | Separate `submitted` / `verified` / `official` data with explicit labelling in every UI and export |
| R5 | Evidence tampering allegations | High | SHA-256 at ingest, private storage, append-only hash-chained audit log, evidence packs |
| R6 | Missing official polling-unit data delays Phases 2, 8 and 11 | Medium | Importer with preview/validation ready early; request data now |
| R7 | USSD service code and WhatsApp template approval lead times | Medium | Request from providers early; simulator lets development proceed |
| R8 | Messaging non-compliance (spam, quiet hours, opt-outs) | Medium | Consent recheck at send time, suppression list, quiet hours, two-person approval for large sends |
| R9 | Offline device loss exposing local data | Medium | Minimal local data, encryption of sensitive local records, remote session revocation, short-lived tokens |
| R10 | Dependency availability in this development environment | Low | See §3 |

## 6. Blocking decisions

**None block Phase 1.** The following need the campaign's approval before the phases noted:

- Approved brand palette and logo (before Phase 10 public launch; the provisional theme can be swapped centrally).
- Hosting provider/region and domains (before Phase 12).
- Data Protection Officer appointment and privacy-notice approval (before Phase 4 goes live).
- Official geographic reference data (before Phase 2 import is used with real data).
