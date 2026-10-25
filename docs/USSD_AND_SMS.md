# USSD and SMS

## Provider callbacks

Configure these URLs in the Africa's Talking dashboard. Each carries the shared secret
`AFRICASTALKING_WEBHOOK_SECRET` (generate a long random value; without it the webhooks return 403).

| Callback | URL |
| --- | --- |
| USSD | `POST https://<host>/webhooks/ussd?key=<secret>` |
| Inbound SMS | `POST https://<host>/webhooks/sms/inbound?key=<secret>` |

Webhooks have no session, cookies or CSRF, are rate limited (`webhooks` limiter) and never log phone
numbers or message text. Delivery-receipt callbacks arrive with the communications engine (Phase 9).

## USSD menu

Language first (1 English, 2 Pidgin), then:

1. **Join as a supporter**: name → LGA → SMS updates yes/no → 18+ → registered (phone verified, `ussd` source, consent recorded against the current privacy notice)
2. **Volunteer registration**: name → LGA → ward → application for coordinator approval
3. **Find my polling unit**: by code, or LGA → ward → unit
4. **Projects in my LGA**: lists approved public projects (Phase 10); until then says none are published
5. **Request a callback**: topic → LGA → a task for the LGA's coordinators; one request per number per 24 hours; staff reveal the number on the task (audited)
6. **Messages and my data**: stop all messages, resume SMS updates, or request deletion (an erasure request, identity verified by the caller's own number)

Navigation on every screen: `0` back (previous page first on lists), `00` main menu, `98` more.

Rules: every screen ≤ 182 characters in both languages (`tests/Feature/Ussd/ScreenLengthTest`, worst-case
names and error lines included); state is encrypted in the cache for `USSD_SESSION_TTL` seconds; a
provider retry of the same step gets the identical answer and does nothing twice; a lost session ends
politely; SMS confirmations are queued, never sent inside the callback.

Measured on the development server (sequential, 200 callbacks): median 10 ms, p95 22 ms. The
200-requests-per-second target is load-tested in Phase 12.

## Inbound SMS commands

Keywords are case-insensitive; extra words after them are ignored.

| Message | Who | Effect | Reply |
| --- | --- | --- | --- |
| `STOP` (`STOPALL`, `UNSUBSCRIBE`, `CANCEL`, `END`, `QUIT`) | anyone | Withdraws consent and suppresses every channel at once | Confirmation, with how to restart |
| `START` (`UNSTOP`, `YES`) | anyone | Grants SMS campaign updates (self-service; lifts their own opt-out) | Confirmation |
| `HELP` (`INFO`) | anyone | — | Command list |
| `REPORT <polling-unit code> <message>` | active staff with `election.report` or `tasks.work`, for a unit in their area | A high-priority task at that polling unit for the area's coordinators | Reference |
| anything else | known staff / unknown number | Recorded as unrecognised | Help text / no reply |

Example: `REPORT 25-15-07-012 Voting materials arrived late`

Inbound messages are stored with a keyed hash of the sender and encrypted text, processed on the
`sms-inbound` queue, and de-duplicated by the provider's message id.

## Simulator

`Workspace → USSD simulator` (`ussd.simulate`) runs the real menu. Practice mode (always on in
production) runs every step in a rolled-back database transaction: nothing is saved and no SMS is queued.
Outside production, practice mode can be switched off; records created are marked SAMPLE.
