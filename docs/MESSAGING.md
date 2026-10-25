# Communications engine

## Flow

1. **Templates** (`messaging.templates`): SMS or WhatsApp, a consent purpose, English and optional Pidgin text.
   Placeholders: `{first_name}`, `{lga}`, `{ward}`, `{reference}`. SMS templates must say how to stop
   (contain "STOP"). WhatsApp templates must name a template approved in WhatsApp Manager; business-initiated
   WhatsApp messages are only ever sent as approved templates.
2. **Campaign draft** (`messaging.create`): template + audience inside the creator's own area (LGAs, wards,
   verification, language, volunteers only) + optional send time. The editor shows matching people, those with
   consent for the channel and purpose, SMS segments, estimated cost and remaining budget.
3. **Approval** (`messaging.approve`): sends at or above `messaging.approval_threshold` recipients need a
   *different* person whose area covers the audience. Smaller sends by someone who may approve go straight
   through. Creator and approver are recorded, audited and shown on the campaign.
4. **Sending**: recipients are listed in the background, then each message is a queued job on the `messaging`
   queue that re-checks, immediately before sending, in this order:
   campaign still sending → outside quiet hours → record not erased/restricted → not suppressed → consent for
   this channel and purpose → budget left → provider not tripped → provider rate limit.
   Anything not sendable is marked *not sent* with the reason; holds (quiet hours, pause, budget) wait and retry.
5. **Results**: delivery receipts (SMS and WhatsApp) update each message; the campaign completes when nothing
   is queued.

## Rules

| Rule | Where |
| --- | --- |
| Opt-outs apply to queued messages | consent and suppression re-checked per message at send time |
| Quiet hours | settings `messaging.quiet_start` / `quiet_end` (local time; default 21:00–08:00) |
| Rate limit | `messaging.rate_per_second` per channel |
| Monthly budget | `messaging.monthly_budget`; submission and approval refuse sends that would exceed it; sending pauses the campaign if it runs out |
| Cost | provider-reported cost when given, otherwise `messaging.sms_segment_cost` × segments or `messaging.whatsapp_message_cost` (set these from your tariff) |
| Retries | temporary failures retried after 1, 5 and 15 minutes, at most 3 attempts; permanent failures (e.g. invalid number) never retried |
| Provider health | 10 consecutive failures pause the channel for 5 minutes, then sending resumes; health tab shows last-hour traffic, failure rate and last error |

Message bodies are rendered at send time and not stored; message rows keep a keyed phone hash, the last four
digits, status, provider id and cost.

## Provider callbacks

| Callback | URL | Security |
| --- | --- | --- |
| Africa's Talking SMS delivery reports | `POST /webhooks/sms/delivery?key=<secret>` | shared secret |
| WhatsApp Cloud API (verification) | `GET /webhooks/whatsapp` | `WHATSAPP_VERIFY_TOKEN` |
| WhatsApp Cloud API (statuses, replies) | `POST /webhooks/whatsapp` | `X-Hub-Signature-256` with `WHATSAPP_APP_SECRET` |

A WhatsApp reply of STOP (or any SMS STOP keyword) suppresses every channel at once.
