# Election-day operations

Phase 11. Three kinds of figure are kept apart everywhere (database, screens, exports):

| Kind | Where it comes from | Label in the app |
|---|---|---|
| **Submitted** | A polling agent copies the polling-unit result sheet and photographs it | "Submitted (unverified)" |
| **Verified** | A second person (`results.verify`, not the submitter) confirms the figures match the photo | "Verified by campaign" — never "official" |
| **Official** | Transcribed by `results.official` staff from an INEC source (IReV, declaration, certified copy), with the source cited | "Official result" |

Campaign tallies are always captioned "Not an official result". Nothing in the platform declares results.

## Setup (`election.setup`)

`Election day → Election setup`: name, date, the parties exactly as on the official ballot (one per line,
`CODE Name`), the opening checklist and turnout reminder times. The platform ships with no parties. An
election needs at least two parties to be activated; activating one closes any other. Parties cannot be
changed once the election is active. Agents can only report while an election is active.

## Polling agents (`polling-agents.view` / `.manage`)

Coordinators assign agents to polling units in their area (one primary, optionally one backup). Assigning
creates the account if needed and grants the `polling_agent` role scoped to that single polling unit;
withdrawing revokes it immediately. The agent receives an SMS with sign-in and SMS-fallback instructions.

## Agent app (`election.report`)

`My polling unit` (mobile first, also linked from the dashboard):

1. **Arrival** — optional browser location; the distance from the unit's recorded location is stored and
   shown to coordinators (flagged above `CHECKIN_DISTANCE_WARNING_M`, default 800 m), never blocked.
2. **Opening checklist** — the configured items; can be updated during the day.
3. **Turnout** — "accredited so far", queue length, note; capped at the unit's registered voters when known.
4. **Incident** — type, severity, description, photos. High and critical incidents notify incident managers
   for the area at once.
5. **Result** — accredited, valid, rejected, total cast and votes per party, with live arithmetic hints, and
   a required photo of the sheet. Agents are told to copy the sheet exactly, even if it is wrong.

Every entry accepts a client UUID, so retries never duplicate. A new sheet from the same agent replaces
their own earlier unreviewed one (kept as "superseded").

## Structured SMS fallback

For agents without data. The polling unit comes from the sender's assignment.

| Message | Effect |
|---|---|
| `ARRIVED` | Records arrival |
| `TURNOUT 120` | Turnout observation |
| `INCIDENT <type> <details>` | Incident (types: VIOLENCE, THREAT, INTIMIDATION, BUYING, MATERIALS, BVAS, LATE, PROCEDURE, ACCESS, OTHER; violence/intimidation are high severity) |
| `RESULT A=350 V=300 R=10 [T=310] ABC=150 XYZ=150` | Result submission (T defaults to V+R); flagged "no photo" until one is uploaded in the app |
| `HELP` | Agents get the election command list |

Provider retries map to the same entry (the provider message id becomes the client UUID). Replies use
GSM-7 characters only, so each fits one SMS.

## Arithmetic validation and discrepancies

`ResultValidator` checks every sheet: party votes sum to valid votes; valid + rejected = total cast; total
cast ≤ accredited; accredited ≤ registered voters (when loaded); party codes exist; no negatives. Problems
are stored with the submission (status "Flagged"), never corrected. Two agents' sheets for the same unit
that disagree are both kept and the later one is flagged as a conflict.

Verification requires a photo and no arithmetic errors, and is refused to the submitter. Only one sheet per
unit can be verified; verifying another supersedes the first. Official figures are compared field by field
with the verified sheet; units that differ appear under `Results → Differs from official` and on the hub.

## Evidence

Original files are stored untouched on the private evidence disk (`EVIDENCE_DISK`) with the SHA-256 of the
bytes as received. Evidence rows cannot be altered (the model refuses changes to path, hash or metadata).
Every view or download is audited and served with `nosniff` and a sandbox CSP. `evidence:verify` (daily
04:15) re-hashes every file and logs any mismatch.

**Evidence packs** (`evidence.export`, own area only) are ZIPs with CSVs of submissions, official figures
and incidents, the original files, a `manifest.json` listing each file's SHA-256 and whether it still
matched while the pack was built, and a README stating that campaign figures are not official results. The
pack's own hash is recorded and sent as `X-Checksum-Sha256`. Downloads are signed links, need a recent PIN,
are audited, and expire after 24 hours.

## Reporting

- **Election day hub**: coverage (agents, arrivals, checklists, sheets, verified), open incidents by
  severity, by-LGA (or by-ward for LGA coordinators) table, verified tally and official tally side by side,
  discrepancy count. Refreshes every 30 seconds.
- **Command centre**: a summary strip while an election is active.
- All figures are limited to the viewer's geographic scope.

## Limitations

- The offline (PWA) queue covers canvassing only; election reports without data go by SMS.
- Turnout is an observation by agents, not an official figure.
- Polling-unit locations and registered-voter counts come only from imported official data; where they
  are missing, distance and the registered-voter checks are skipped.
