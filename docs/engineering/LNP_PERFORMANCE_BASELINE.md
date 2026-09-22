# LNP performance baseline (pre-release)

**Status:** Official pre-release baseline · Core not redeployed  
**Image:** `ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7`  
**Surfaces:** Attention `/app` and RO `#1747` (`repair_order_id` 1747)  
**Repeat after:** reporting-only recreate (`sha256:f4abe244…`), then again after a separately approved poller change

These numbers are the comparison point. They are not a profile of every RO, and they are not permission to deploy.

## Official warmed samples (2026-09-22)

Three samples after warmup. Medians.

| Surface | HTML bytes | Laravel kernel | HTTP TTFB inside `core` | Public HTTPS TTFB (origin) |
| --- | ---: | ---: | ---: | ---: |
| Attention `/app` | 234,417 | 823 ms | 722 ms | 867 ms |
| RO `#1747` | 698,188 | 992 ms | 1,114 ms | 1,210 ms |

Public TLS handshake on those samples: 31–60 ms. From a remote Mac, unauthenticated `/up` TTFB was 151–261 ms.

Client polls on this image (`app-BgFE89jE.js`): call-queue `refresh()` every **5 s**; comms ingest **2.5 s**. Queue root: `ops-call-queue--poller-only` with `x-init="init()"`.

A later uninstrumented RO kernel run on the same image measured 1,424 / 2,263 / 1,510 ms (median **1,510 ms**). Treat the official 992 ms as a warmed best-of-run, not a ceiling. RO show is about **one to two seconds** in Laravel before the browser paints.

## RO `#1747` Laravel breakdown

Instrumented on the same image (query listener on). Listener overhead inflates totals; use this for **where** time went, not as a replacement for the table above.

Controller vs `view()->render()` (medians, three samples after warmup):

| Bucket | Median | Share of controller+view |
| --- | ---: | ---: |
| View render excluding SQL | 597 ms | 57% |
| SQL during view (lazy relations / Blade queries) | 311 ms | 30% |
| Controller PHP excluding SQL | 106 ms | 10% |
| SQL during controller | 39 ms | 4% |
| **Controller + view** | **1,053 ms** | 100% |

Queries on that split: **67** in the controller, **294** while rendering the view (**361** total). HTML still ~698 KB.

SQL in the last split sample, by table (ms):

| Table | Queries | ms |
| --- | ---: | ---: |
| `information_schema` | 82 | 224 |
| `conversation_messages` | 32 | 37 |
| `estimate_documents` | 12 | 21 |
| `customers` | 28 | 20 |
| `leads` | 18 | 20 |
| other shop tables | 189 | ~81 |

`information_schema` is schema probing (`hasTable` / column lists), not RO business data. It was most of the SQL time in that sample. The view still issues hundreds of real table queries (`estimate_documents` 12×, `conversation_messages` exists 16×, customer phone lookups 16×).

Controller work (eager loads, totals, projections) is the small slice. Most of the second is **Blade producing ~700 KB of HTML**, plus **lazy queries from that render**, plus **schema introspection**.

The poller fix can cut background traffic. It should not be assumed to remove this RO show cost. Schema, lazy-query, and Blade-size work is a third stream: [RO_REQUEST_TIME.md](RO_REQUEST_TIME.md). Do not put it in the reporting recreate or the poller image.

## How to repeat

Same image pin, same two URLs, warmup then three samples:

1. Laravel kernel (PHP `hrtime` around `HttpKernel::handle`)
2. HTTP TTFB inside `core` to `http://127.0.0.1`
3. Public HTTPS TTFB from the origin host

For another breakdown, split `RepairOrderShowController` return vs `$view->render()` and `DB::listen` query times. Do not leave a listener enabled.

Do not Coolify Deploy. Do not mix this file with a recreate.
