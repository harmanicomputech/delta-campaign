# Launch readiness assessment

Status on 25 October 2026, at the end of Phase 12. **Verdict: a tested production deployment candidate,
not yet ready to launch.** The software is feature-complete for the brief and tested. Launch is blocked
by content, accounts and external reviews that only the campaign can supply, by staging load tests, and
by the pilot.

`php artisan campaign:launch-check` checks the technical part automatically. It must report no blockers
on the production environment before go-live.

## What is done and tested

| Area | Evidence |
|---|---|
| Functionality, Phases 1–11 | `docs/IMPLEMENTATION_PLAN.md`. Each phase was browser-verified |
| Automated tests | 425 PHPUnit tests (feature, unit, integration, security); CI on PHP 8.3 and 8.4 |
| Security | `docs/SECURITY_REVIEW.md`: route-protection tests, output and SQL review, dependency audits clean, 6 findings fixed |
| Accessibility | axe-core: 0 WCAG 2.1 AA violations across 92 page/theme combinations |
| Performance | `docs/LOAD_TESTING.md`: USSD at 200 requests/s with p95 11 ms on one 4-vCPU host. Sync service cost measured. Public site needs a cache or more replicas |
| Backups | Encrypted backup and restore test passed (row counts and audit chain) |
| Monitoring | `/health` (database, PostGIS, Redis, cache, Horizon, scheduler heartbeat, SMS breaker), structured logs, daily audit and evidence integrity checks |
| Operations | `docs/RUNBOOK.md`, `docs/DEPLOYMENT.md` |

## Blockers: must be resolved before public launch

| # | Item | Owner |
|---|---|---|
| 1 | Approved candidate name, office, tagline, About and Manifesto text, logo and brand colours (all are placeholders) | Campaign communications |
| 2 | Privacy notice approved by the DPO and counsel, replacing the placeholder notice; NDPA compliance assessment and record of processing | Data Protection Officer |
| 3 | Official ward and polling-unit data imported from INEC sources (`geography:import`) | Data lead |
| 4 | Production Africa's Talking account (sender ID, USSD code, credentials, callbacks configured with a strong secret); WhatsApp Business templates approved, if used | Operations |
| 5 | Production environment passes `campaign:launch-check`: HTTPS, secure cookies, HSTS, database TLS, real SMS driver, no SAMPLE data, at least two super administrators with 2FA | Engineering |
| 6 | Staging load tests with k6 (`tests/Load/`), plus a CDN/page cache or more web replicas for public traffic | Engineering |
| 7 | Independent penetration test, with findings fixed | Engineering and an external tester |
| 8 | Pilot completed (below) and its findings resolved | Platform owner |
| 9 | Native-speaker review of all Pidgin text | Communications |
| 10 | Off-site backup storage and the offline decryption-key custody agreed; first production restore test | Operations |

## Known limitations (accepted for launch unless the pilot shows otherwise)

- Offline election reporting uses the SMS fallback; the PWA offline queue covers canvassing only.
- Live updates use polling (30–60 s) rather than WebSockets. Reverb can be added later without screen changes.
- No static analysis (Larastan could not be installed in the development environment). Add it in CI.
- `'unsafe-eval'` in the CSP (Alpine/Livewire standard builds).
- Pest is not used (PHPUnit 12 throughout).

## Pilot plan

**Scope:**

- one LGA and its wards for two weeks;
- 1 LGA coordinator, 5 ward coordinators, about 30 volunteers;
- 10 polling agents in a SAMPLE training election;
- 2 content editors and 1 rapid-response officer;
- about 200 supporters registered through the real channels (web, USSD, staff).

**Must work:**

- supporter registration on all three channels, with consent records checked by the DPO;
- volunteer application → approval → training → walk list → offline canvassing on low-end Android
  phones, with at least one sync after a day offline;
- a message campaign with independent approval to pilot supporters; then STOP replies honoured;
- CMS: draft → review → publish of a news item and a project record;
- event registration and door check-in by QR;
- election rehearsal: agents arrive, complete checklists, report turnout, an incident and results by app
  **and** by SMS; verification, official-figure entry from a mock source, discrepancy review, and an
  evidence pack;
- a restore test, and one practised incident (an SMS provider outage simulated with a wrong API key on
  staging).

**Measure:**

- task completion without help (target 90%);
- time to register on USSD (target under 2 minutes);
- agent result submission time (target under 5 minutes);
- sync success rate (target 99%);
- support questions per user;
- accessibility issues found by keyboard and screen-reader users.

**Exit criteria:** no open critical or high defects; all "must work" items demonstrated; the DPO signs off
consent and data handling; the platform owner signs off.
