# RO request-time performance

**Status:** Independent workstream · not started  
**Not:** reporting-only LNP recreate · call-queue poller (`cf169e76`)

RO show is slow because of the request itself. Baseline: [LNP_PERFORMANCE_BASELINE.md](LNP_PERFORMANCE_BASELINE.md). Poller work cuts background traffic. It does not own this cost.

Do not fold schema, eager-load, or Blade-size work into either pending release.

## Evidence (RO `#1747`, current production image)

Warmed official kernel median **992 ms**; a later uninstrumented run **1,510 ms**. Instrumented controller+view ~1.05 s:

| Bucket | Median |
| --- | ---: |
| Blade excluding SQL | 597 ms |
| SQL during render | 311 ms |
| Controller PHP | 106 ms |
| Controller SQL | 39 ms |

361 queries, **294 during render**, **82 information_schema / 224 ms**. HTML ~698 KB.

## Priorities (investigation, not claimed savings)

1. **Schema probes.** Cache or skip `hasTable` / column-list checks on the hot path where the schema is already known for this install. `ShopCommunicationsSchema` is an example: it must keep working on shops that are missing voice tables. Do not assume every Core install has LNP’s schema.
2. **Lazy queries in Blade.** Batch or preload the repeats: `estimate_documents`, conversation existence, customer phone lookups. Same RO after the change. No new N+1 elsewhere on the workspace.
3. **Render size.** 597 ms excluding SQL will remain after query cuts. Shrinking or splitting the 698 KB show template is a separate pass.

Measure each change against the official baseline protocol. Do not treat query-count drops as page-time proof.

## Compatibility

Optional tables and older installs stay fail-soft. RO behavior, totals, and workspace chrome stay the same. Fail closed only where the code already does.

## Sequence

1. Reporting-only LNP recreate (`sha256:f4abe244…`) — own approval. Repeat baseline.
2. Poller release — own approval. Repeat baseline.
3. This workstream — own approval. Repeat baseline on RO `#1747` and Attention.
