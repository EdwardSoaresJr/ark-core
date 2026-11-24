# Issue #251 / PR #252 — acceptance matrix (follow-up)

Stop for Carlos/owner visual review. No merge. No deploy. No SaaS pin.

## Commit

Recorded on push (see PR body / comment for exact SHA).

## Owner review follow-ups addressed

| # | Requirement | Resolution |
| --- | --- | --- |
| 1 | Bounded list performance | List projects **only the current page** (`PAGE_SIZE=50`). Lane totals are exact cheap buckets. Board cap unchanged (12). |
| 2 | Tab counts vs filtered rows | Badge counts = **queue totals**. Summary line = `Showing X–Y of Z matching · Queue total N`. Server-side `lane`/`q`/`tech`. |
| 3 | Sort / a11y | Server sort links with `aria-sort`. List queue controls are **nav pills/links**, not a fake `tablist`. Board uses `aria-pressed` toolbar buttons. Column labeled **Attention** (ARK attention, not user priority). |
| 4 | Mobile / cookie / role smoke | Cookie layout still works; forbidden without ops access; mobile cards + drill-through verified in browser. |
| 5 | Evidence | Screenshots + `PERF_LIST.json` (120 RO synthetic lane). |

## Screenshots

| Viewport / flow | Path |
| --- | --- |
| List · desktop | `screenshots/list-desktop.png` |
| List · lane filtered | `screenshots/list-lane-filtered.png` |
| List · RO drill-through | `screenshots/list-drillthrough.png` |
| List · mobile ~390px | `screenshots/list-mobile.png` |
| Board · desktop (preserved) | `screenshots/board-desktop.png` |

## Performance (synthetic)

See `PERF_LIST.json` from focused test (120 waiting-approval ROs, list page 1):

- rendered rows: 50 (`PAGE_SIZE`)
- filtered total: 120
- query count: ~38
- elapsed: ~114 ms (test env)

## Focused tests

`tests/Feature/Operations/JobBoardListViewTest.php` — 11 passing  
Board regression subset of `WorkboardTriageTest` — passing

## Still out of scope

No merge, staging/production deploy, SaaS pin, ARK Sites, booking, billing, or customer production data changes.
