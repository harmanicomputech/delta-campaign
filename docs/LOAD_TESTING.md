# Load testing

Phase 12. The brief's targets are **200 USSD callbacks per second**, **1,000 concurrent synchronising
devices** and **5,000 concurrent public-website visitors**. This page records what was measured, on what,
and what still has to be measured on production-like infrastructure. Nothing here claims a target is met
in production.

## Measured in development (25 October 2026)

**Environment:** one 4-vCPU container running PHP 8.3 with OPcache, PHP's built-in server with 8 workers
(not nginx/php-fpm), PostgreSQL 16 and Redis on the same machine, `APP_DEBUG=false`, and cached routes and
views. The load generator ran on the same machine, which takes CPU from the server, and opened one
connection per request. Production (nginx + php-fpm on dedicated hosts) should do better; treat these as
lower bounds.

### USSD callbacks: target met on one small host

| Run | Requests | Throughput | p50 | p95 | p99 | Max | Errors |
|---|---|---|---|---|---|---|---|
| Fixed 200 req/s for 60 s (mixed new sessions, menu choices, paging) | 12,001 | 200/s | 9 ms | 11 ms | 16 ms | 255 ms | 0 |
| Saturation, 40 concurrent, 30 s | 13,827 | 461/s | 86 ms | 101 ms | 111 ms | 189 ms | 0 |

Africa's Talking expects a USSD reply within a few seconds. At the target rate, replies took about
10 ms.

**Finding fixed.** The webhook rate limit was 12,000 per minute per IP: exactly the 200/s target, so any
burst would have been rejected with 429. Callbacks come from a few provider addresses, so the limit is
now `WEBHOOK_RATE_PER_MINUTE` (default 30,000, which is 500/s).

### Field-app sync: service cost measured, not full HTTP

The API's per-person limit (30 requests/min) means one test account cannot simulate 1,000 devices, and
creating 1,000 signed-in test accounts was out of scope for development. The sync service itself was
timed instead: 200 batches of 10 new contact records each, inside a rolled-back transaction.

| Batch | p50 | p95 | Max |
|---|---|---|---|
| 10 contacts (validate, de-duplicate, authorise, write, audit) | 37 ms | 51 ms | 75 ms |

1,000 devices syncing once a minute is about 17 batches per second, roughly 0.6 CPU-seconds per second
of service time plus HTTP and session overhead (about 5–10 ms per request, judging by the USSD numbers).
That suggests 2 web vCPUs are enough. **This needs confirming with `tests/Load/sync.js` on staging.**

### Public website: needs caching or more web capacity for 5,000 visitors

Measured with 40 concurrent visitors and no think time:

| Page | Throughput | p50 | p95 | p99 | Average size |
|---|---|---|---|---|---|
| Home | 126/s | 321 ms | 385 ms | 426 ms | 33 KB |
| News | 156/s | 258 ms | 305 ms | 320 ms | 21 KB |
| Events | 151/s | 267 ms | 315 ms | 334 ms | 22 KB |
| Polling-unit lookup | 152/s | 266 ms | 313 ms | 329 ms | 27 KB |

5,000 concurrent visitors reading a page every 3–10 seconds is about 500–1,700 page views per second.
That is 4–12× what this one 4-vCPU container served. Before launch, do one of these:

1. Put a CDN or reverse-proxy cache in front of anonymous public `GET` pages. Pages with forms carry a CSRF
   token, so cache only pages without forms, or move forms behind a client-side token fetch.
2. Run 4–8 web replicas behind the load balancer. The app is stateless: sessions are in the database,
   cache and queues in Redis.

Then confirm with `tests/Load/public.js`.

## How to run

- **Staging, with k6:** `tests/Load/ussd.js`, `tests/Load/sync.js` and `tests/Load/public.js`. Each has
  thresholds that fail the run if p95 latency or the error rate is too high; the usage is at the top of
  each file. Run from an address that the webhook limit allows, and never against production during
  campaigning.
- **Development:** the measurements above came from a small Node load generator. It is not kept in the
  repository because k6 is the supported tool.

## Constrained networks

The field app works offline: an encrypted queue syncs on reconnect (`docs/SECURITY.md`). Polling agents
without data use the SMS fallback (`docs/ELECTION_DAY.md`). Low-data mode stops background polling.
Public pages are 20–35 KB of HTML (gzip reduces this further) plus cached, fingerprinted assets.
