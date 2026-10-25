# Operational runbook

For the people who run the platform day to day. Commands assume a shell in a `web` container (or the
equivalent host) with the production environment loaded. Fill in the contacts before launch.

## Contacts and roles

| Role | Responsibility | Contact |
|---|---|---|
| Platform owner | Approves releases and maintenance windows | [PLACEHOLDER: name, phone] |
| On-call engineer | Alerts, deployments, restores | [PLACEHOLDER: rota] |
| Data Protection Officer | Personal-data incidents, data-subject requests, NDPC notification | [PLACEHOLDER] |
| Communications lead | Message approvals, public statements | [PLACEHOLDER] |
| Africa's Talking account manager | SMS/USSD provider issues | [PLACEHOLDER] |

## Daily checks (10 minutes)

1. `GET /health`: every check `ok`. `degraded` needs attention today; `failed` is an incident.
2. Horizon dashboard (`/horizon`, needs the `system.horizon` permission): no failed jobs, and queue wait under a minute.
3. Workspace → Messaging → Provider health: delivery rate normal, circuit breaker closed.
4. Logs: `Audit chain verification failed` or `Integrity check failed` are incidents (see below).
5. Disk: database and evidence volumes under 70%.

## Scheduled jobs

These run from the single `scheduler` replica.

| Time (UTC) | Job |
|---|---|
| Every minute | `messaging:tick`, `content:publish-due`, scheduler heartbeat |
| Hourly | `tasks:process-overdue` (:07), exports prune |
| 00:05 | `audit:checkpoint` (writes the chain head to the external log stream) |
| 02:30 | `audit:verify` |
| 03:00 | `auth:prune` |
| 04:15 | `evidence:verify` (re-hashes evidence and expires old evidence packs) |
| Monday 01:30 | `privacy:apply-retention` |

If the heartbeat stops, `/health` reports `scheduler: degraded` within 3 minutes.

## Releases

Follow `docs/DEPLOYMENT.md`. In short:

1. Green CI.
2. Run `scripts/ops/backup.sh`.
3. Run `php artisan migrate --force` as a one-off task.
4. Roll out `web`, then `horizon` (`php artisan horizon:terminate`), then `scheduler`.
5. Check `/health`.
6. Before the first release and after configuration changes, run `php artisan campaign:launch-check`.

**Freeze:** no releases from 7 days before election day until results are certified, except security fixes
approved by the platform owner.

## Backups and restores

| What | How | When |
|---|---|---|
| Database and private files | `scripts/ops/backup.sh`, encrypted with `age` to `BACKUP_AGE_RECIPIENT` | Daily; hourly during election week; before every release |
| Off-site copy | Sync `BACKUP_DIR` to separate storage in another account and region | After each backup |
| Restore test | `scripts/ops/restore-test.sh`: backup, restore into a scratch database, compare row counts, verify the audit chain, drop | Monthly, and in the week before election day |
| Managed database snapshots | The provider's point-in-time recovery, if available | Continuous |

The decryption key (`BACKUP_AGE_IDENTITY`) is kept **offline**, for example in a safe with two
custodians. It is never stored on the servers.

On 25 October 2026 the restore test passed in development:

- 11 MB encrypted dump;
- restore took 2 s;
- row counts matched in all 67 tables;
- the audit chain verified on the restored copy.

### Restoring production

1. Declare an incident. Put the site in maintenance mode: `php artisan down --retry=60`.
2. Restore into a **new** database: `scripts/ops/restore.sh <db-backup> campaign_restored`. The script
   refuses to overwrite an existing database.
3. Verify it: `DB_DATABASE=campaign_restored php artisan audit:verify` and spot-check recent records.
4. Point `DB_DATABASE` at the restored database, or rename the databases (`ALTER DATABASE … RENAME`),
   and restart `web`, `horizon` and `scheduler`.
5. Restore files: decrypt the matching `files-*.tar.gz.age` into `storage/app`.
6. `php artisan up`. Record the data-loss window (backup time to incident time) in the incident report.

## Incidents

### Personal-data breach (suspected or confirmed)

1. Contain it:
   - suspend affected accounts (Users → Suspend, which ends their sessions);
   - revoke credentials;
   - if needed, put the site into maintenance mode.
2. Tell the Data Protection Officer **immediately**. Under the NDPA, the controller must notify the NDPC
   within **72 hours** of becoming aware of a breach likely to cause risk, and inform affected people
   without undue delay where the risk is high.
3. Preserve evidence: audit log (`audit:verify`), login history, application logs. Do not delete anything.
4. Record what data, how many people, and when, using `Audit log` filters and exports.
5. After the incident: write a root-cause note, list the fixes, and review access.

### Audit chain verification failed

Treat it as possible tampering.

1. Do not "fix" rows.
2. Note the broken entry id from the log.
3. Compare it with the checkpoint written by `audit:checkpoint` to the external log stream.
4. Restore a copy from before the break into a scratch database and compare.
5. Escalate to the platform owner and the DPO.

### Evidence integrity failed (`evidence:verify`)

The stored file no longer matches its recorded hash.

1. Do not overwrite it.
2. Take the original from the most recent backup that verifies, and check its SHA-256 against the
   database record.
3. Record the incident. Evidence packs built since then flag the file as `integrity_ok: false`.

### SMS provider outage or high failure rate

The circuit breaker pauses sending automatically. Messages wait; they are not dropped.

1. Check the provider's status page and contact the account manager.
2. `/health` shows `sms: degraded` while the breaker is open.
3. Don't resume large campaigns until the delivery rate recovers.

One-time sign-in codes also go by SMS. During a long outage, staff who are already signed in carry on;
new sign-ins wait.

### USSD slow or failing

1. Check `/health` and web CPU.
2. Replies should take well under a second; the platform measured about 10 ms at 200 requests/s.
3. If the provider reports timeouts, scale the `web` replicas.
4. Check that the webhook rate limit (`WEBHOOK_RATE_PER_MINUTE`) is not rejecting traffic. The
   symptom is 429s in the access log.

### Queue backlog

Horizon shows rising wait times.

1. Check for failing jobs first (Horizon → Failed).
2. Scale `horizon` replicas, or raise `maxProcesses` for the affected supervisor in `config/horizon.php`.
3. Message sending has its own supervisor, so it cannot block OTP delivery.

### Compromised staff account

1. Suspend the account; sessions end immediately.
2. Review its login history and audit entries.
3. Re-issue access through an administrator-assisted phone change (`docs/SECURITY.md`).

## Election day

**T−14 days:**

- election set up and activated in training mode with the SAMPLE election removed;
- parties checked against the official ballot by two people;
- all agents assigned;
- each agent has signed in once and sent a test `HELP` by SMS.

**T−7:**

- release freeze;
- restore test;
- load test on staging (`tests/Load/`);
- `campaign:launch-check` clean;
- on-call rota confirmed.

**T−1:**

- hourly backups on;
- Horizon and database capacity scaled up;
- SMS balance topped up;
- printed SMS-fallback cards distributed to agents.

**Election day:**

- the command centre and election-day hub are watched continuously;
- incident managers clear critical incidents within 15 minutes;
- result verifiers work through `Results → To review`.

**After:**

- official figures are recorded only from INEC sources;
- discrepancies are reviewed;
- evidence packs are built for legal counsel;
- the election is closed in Election setup.

## Key management

- `APP_KEY`, `PHONE_HASH_KEY` and `FIELD_ENCRYPTION_KEY` are generated once (`php artisan campaign:keys`)
  and stored in the secret manager.
- **Never rotate `PHONE_HASH_KEY` or `FIELD_ENCRYPTION_KEY` casually.** Changing them makes existing
  phone look-ups and encrypted fields unreadable. `campaign:keys --force` refuses without explicit intent.
  A rotation needs a planned re-encryption migration.
- Rotate webhook secrets (Africa's Talking `?key=`, WhatsApp app secret) at the provider and in the
  environment at the same time, during a quiet period.
