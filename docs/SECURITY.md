# Security model

## Identity and sign-in

| Control | Implementation |
| --- | --- |
| Identifier | Nigerian mobile number, normalised to E.164. Stored encrypted (`FIELD_ENCRYPTION_KEY`, AES-256-GCM) with a keyed HMAC-SHA256 hash (`PHONE_HASH_KEY`) for look-ups and uniqueness, plus the last four digits for display |
| First factor | 6-digit one-time code by SMS (`OtpService`). Only an HMAC of the code is stored; 5-minute expiry; 5 attempts (atomic increment); single use (atomic consume); 60-second resend cooldown; 5 codes per number per hour and 30 per IP per hour; route throttles per IP and session |
| Non-enumeration | A challenge is created for every valid number, and the response, redirect and page are identical whether or not it is registered. Codes are sent only to active accounts, via the queue, so timing is similar. Tested |
| Second factor | TOTP (RFC 6238, verified against the RFC vectors) for every holder of a privileged role (`super_admin`, `dg`, `state_director`, `data_protection_officer`). Enrolment is enforced before any workspace access. One-step clock drift is accepted, and used steps are rejected (replay protection). 8 single-use recovery codes, stored encrypted |
| PIN re-entry | Optional 4–6 digit PIN (bcrypt), with weak PINs rejected and a 15-minute lockout after 5 failures. Used to unlock idle sessions and to confirm sensitive areas. Without a PIN, a fresh one-time code is used |
| Idle lock | After 30 minutes without page activity (`AUTH_IDLE_LOCK_MINUTES`). Background Livewire polling does not count as activity; locked sessions answer Livewire requests with 419 |
| Sensitive-area confirmation | `pin.confirm` middleware (audit log, later exports and evidence) re-asks after 15 minutes (`AUTH_PIN_CONFIRM_MINUTES`) |
| Sessions | Database sessions, regenerated at sign-in, each recording its device. Users list and end their sessions; administrators end all sessions of an account. No "remember me" |
| Devices | Random 40-character, long-lived, HttpOnly cookie; only its keyed hash is stored. A new device produces a login event and an SMS alert. Removing a device ends its sessions |
| Suspension | Immediate: all sessions deleted, and any request from a suspended account signs it out (`EnsureAccountIsActive`). `Gate::before` denies everything to suspended accounts |
| Account recovery | Administrator-assisted phone change. A code sent to the **new** number must be entered. This resets the PIN and authenticator, ends all sessions and alerts the old number |
| Login history | `login_events` for every request, failure, lock, new device, unlock, logout and revocation. Suspicious events are also written to the application log for alerting |
| Bootstrap | `php artisan campaign:create-admin {phone} {name}` (audited as `console`) |

## Authorisation

- **Permissions** (`config/permissions.php`, 61) say *what*; **scoped role assignments** say *where*
  (state, district, LGA, ward or polling unit). Seeded idempotently by `RolesAndPermissionsSeeder`.
- Permission slugs are Gate abilities (`can:supporters.view`). Policies handle record-level checks.
- **Geographic scope** (`GeoScope`) is resolved per permission from the user's active assignments and
  applied with `Model::query()->visibleTo($user, 'permission')` as OR-ed, indexed `IN` predicates on
  denormalised columns (see `docs/DATA_MODEL.md`). An out-of-scope record is a 404, never a 403, so
  its existence is not revealed.
- **Anti-escalation** (`UserAdministration`):
  - You can grant only roles whose permissions you hold, ignoring self-service permissions such as
    working your own tasks.
  - The assignment must be at the role's level and inside your own scope.
  - Privileged roles need `roles.assign-privileged`; only super administrators grant `super_admin`.
  - The last super administrator cannot remove their own role.
- Livewire component requests re-run the workspace middleware (persistent middleware), and components
  re-authorise in `mount()` and in each action.

## Audit log

- `audit_logs` is append-only. PostgreSQL triggers reject `UPDATE`, `DELETE` and `TRUNCATE`.
- Each entry stores `hash = SHA-256(previous_hash | canonical JSON(entry))`. Writers serialise with a
  transaction-scoped advisory lock, so the chain cannot fork. An entry rolls back with its transaction.
- Sensitive attributes (encrypted columns, hashes, PIN and TOTP secrets, tokens) are never written.
  Phone numbers in free text are masked.
- `php artisan audit:verify` (daily at 02:30, and on demand from the audit page) detects altered,
  removed or reordered entries. `audit:checkpoint` (daily at 00:05) writes the latest hash to the
  shipped log stream, so even a chain rewritten by a database superuser is detectable against those
  external checkpoints.
- Recorded so far: sign-in and sign-out, two-factor and recovery events, PIN set, session and device
  revocation, user creation, role assignment and revocation, suspension and reactivation, phone changes,
  imports, audit viewing and verification.

## Transport and browser

See `ARCHITECTURE.md` §8 (CSP with per-request nonce, HSTS, frame, referrer and permissions policies,
request IDs, log redaction).

## Offline field app (Phase 7)

- The service worker caches only the offline shell (`/offline`), the manifest icons and fingerprinted
  build assets. Signed-in pages, Livewire requests and API responses are never cached.
- Walk lists assigned to the person, and contacts captured without a connection, are stored in
  IndexedDB encrypted with AES-GCM under a per-device, **non-extractable** WebCrypto key. This protects
  data at rest from casual inspection of the device; it does not protect against malware in the browser
  or a person using the unlocked device.
- Cached lists hold names and references only, never phone numbers. They are deleted on sign-out and when
  a different person signs in on the device. Unsent captures stay (encrypted) until the same person syncs;
  the server re-checks every item against the current assignment.
- `/api/v1/sync` uses the normal workspace session (sign-in, second factor, idle lock) and CSRF
  (`X-XSRF-TOKEN`). Items are idempotent on their client UUID, a UUID belonging to another person is
  refused, captures older than 14 days are refused, and device times in the future are clamped.

## Known limitations

- The audit chain is verified in full, synchronously. At millions of entries, verification will move to
  a queued job with incremental checkpoints (Phase 12).
- One-time codes depend on SMS delivery. Authenticator codes are the fallback for privileged users;
  recovery for others is administrator-assisted.
- `'unsafe-eval'` remains in the CSP for Alpine (`ARCHITECTURE.md` §8).
