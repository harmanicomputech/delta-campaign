# Security and accessibility review (Phase 12)

An internal review of the whole platform against the security model in `docs/SECURITY.md` and the
privacy controls in `docs/DATA_PROTECTION.md`. **This does not replace an independent penetration test**,
which is listed as a launch blocker in `docs/LAUNCH_READINESS.md`.

## Automated guards added

| Guard | Where |
|---|---|
| Every `workspace/*` and `api/*` route carries the `workspace` middleware stack (sign-in, second factor, idle lock, active account) | `tests/Feature/Security/RouteProtectionTest.php` |
| Every webhook route requires the shared secret or the WhatsApp signature | same |
| A guest opening any parameterless workspace page is sent to sign-in | same |
| A volunteer cannot open 17 privileged screens (users, audit, supporters, settings, imports, command centre, messaging, content, rapid response, contact inbox, election hub, results, incidents, evidence packs, election setup, USSD simulator, approvals) | same |
| Markdown cannot inject scripts, event handlers or `javascript:` links | same |
| Session cookie is HttpOnly and SameSite | same |
| Launch check never prints secret values | `tests/Feature/Operations/LaunchCheckTest.php` |
| End-to-end journeys keep the audit hash chain intact | `tests/Feature/Integration/EndToEndTest.php` |

## Findings and fixes

| # | Finding | Severity | Status |
|---|---|---|---|
| 1 | The webhook rate limit (12,000/min per IP) equalled the 200/s USSD target, so legitimate bursts from the provider would have been rejected | Medium (availability) | Fixed: `WEBHOOK_RATE_PER_MINUTE`, default 30,000 |
| 2 | Event, incident, polling-unit and evidence pages returned **403** for records outside the viewer's area, which reveals that they exist. The documented model is 404 | Low (information disclosure) | Fixed: 404, and tests updated |
| 3 | Livewire's temporary-upload limit (12 MB) was below the 15 MB evidence limit, so large photos failed with a confusing error | Low (reliability) | Fixed: follows `EVIDENCE_MAX_KB` |
| 4 | nginx served `/robots.txt` as a static file that no longer exists, so production would have returned 404 instead of the generated file | Low (SEO / crawler control) | Fixed |
| 5 | Super administrators could approve their own CMS content, unlike every other two-person workflow (found in Phase 10) | Medium (integrity) | Fixed |
| 6 | Messaging tests depended on the time of day (found in Phase 11) | Low (test reliability) | Fixed |

## Reviewed with no change needed

- **Output escaping:** six unescaped outputs (`{!! !!}`). Markdown is rendered with HTML stripped and unsafe
  links removed. Icon SVG paths come from local files. JSON-LD is encoded with `JSON_HEX_TAG`. The XML
  prologs are constants.
- **SQL:** queries use the query builder with bindings. Free-text search escapes `%`, `_` and `\`
  (`WithTableState::likeTerm`). Raw SQL fragments contain no user input.
- **Uploads:** checked by type and size on the server. Images for the public site are re-encoded, which
  strips metadata. Evidence is stored unchanged on private storage with a SHA-256 hash, cannot be modified,
  and is served with `nosniff` and a sandbox CSP after an audited permission check. CSV exports neutralise
  spreadsheet formulas.
- **Webhooks:** shared secret or HMAC signature, idempotent on the provider's message id, rate limited, and
  outside the web session.
- **Secrets:** stored only in the environment and never logged. Phone numbers, OTPs, PINs and tokens are
  not logged in plaintext (`docs/SECURITY.md`).
- **Dependencies:** `composer audit` and `npm audit` found no known vulnerabilities on 25 October 2026.
  Both run in CI.
- **Headers:** a nonce-based CSP, HSTS in production, `frame-ancestors 'none'`, `nosniff` and a strict
  Referrer-Policy (`tests/Feature/Http/SecurityHeadersTest.php`).

## Accepted risks and residual items

- **`'unsafe-eval'` in `script-src`:** needed by the standard builds of Alpine and Livewire. Scripts are
  still restricted to same-origin and nonce, so injected inline script cannot run. Moving to Alpine's
  CSP build would remove it, but that build needs a refactor of inline `x-data` expressions.
- **`$guarded = ['id']` on models:** services build attribute arrays explicitly and Livewire properties are
  typed, so no request input is mass-assigned directly. A static-analysis pass (Larastan) would make this
  stronger, but it could not be installed here because GitHub downloads are blocked.
- **Client IPs behind a proxy:** rate limits key on the client IP. Production must set `TRUSTED_PROXIES`
  correctly, or every request will appear to come from the load balancer.
- **Pending externally:** independent penetration test, NDPA compliance assessment by the Data Protection
  Officer and counsel, and a provider security review for Africa's Talking and WhatsApp.

## Accessibility

`tests/Browser/accessibility.cjs` runs axe-core (WCAG 2.0/2.1 A and AA rules) on 46 pages in light and dark
themes. That covers the public site, every staff module, and the polling-agent screens on a phone.

First run: 3 rule groups failed.

- **Colour contrast:**
  - sidebar group labels 4.4:1;
  - "subtle" text 4.3:1 in light mode;
  - in dark mode, the primary green and the danger red failed both as text and as button fills under white text.
- **An unnamed logo link** on mobile.
- **An `aria-label` on a plain `div`** (loading skeleton).

Fixes:

- **Tokens:**
  - subtle text is now `#5f6964` (4.9:1 or better);
  - dark mode uses a lighter primary (6.2:1 as text);
  - in dark mode, primary and danger buttons use dark text (6.8:1);
  - a new `on-danger` token was added.
- **Markup:** the logo link has a name, and the skeleton has `role="status"`.

**Second run: 0 violations on all 92 page/theme combinations.** Automated checks find roughly a third of
accessibility problems. Keyboard-only and screen-reader passes (NVDA/TalkBack) by a person are part of
the pilot.
