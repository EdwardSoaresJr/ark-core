# Issue #251 / PR #252 — table-first Job Board acceptance

Stop for Carlos/owner visual review. No merge. No deploy. No SaaS pin.

## Commit

Recorded on push (see PR comment for exact SHA).

## Owner direction (comment 7120053252)

Kanban columns and Board/List toggle are **removed** from the Job Board. One polished table-first board with horizontal shop lane tabs is the primary UI. Shared home-card/triage components remain for other surfaces.

## Requirements addressed

| # | Requirement | Resolution |
| --- | --- | --- |
| 1 | Table-first primary Job Board | `/app` always renders the dense RO table; no Kanban columns, no Board/List toggle. |
| 2 | Horizontal workflow tabs | Shop lane tabs (All + configured lanes) with queue-total badges; server `lane` filter. |
| 3 | Density / columns | RO #, Customer, Vehicle, Concern/Job, Status, Tech, Promised, Parts, Total, Attention, Activity, Updated, open. |
| 4 | Preserve workflows/actions | See `ACTION_PRESERVATION.md`. Row links + Check In + RO workspace cover former card affordances. |
| 5 | Legacy view/cookie | `?view=board`, `?view=list`, cookie `ark_job_board_layout` normalize to the unified table. |
| 6 | Bounded performance | Page size 50; page-only projection; cheap lane totals. |
| 7 | Honest counts | Badge = queue totals; summary = `Showing X–Y of Z [matching] · Queue total N`. |
| 8 | Mobile | Compact stacked rows with RO/vehicle/status/next action + open affordance. |
| 9 | Evidence | Full-page desktop/mobile screenshots + lane filter + RO drill-through + `PERF_LIST.json`. |

## Screenshots

| Viewport / flow | Path |
| --- | --- |
| Unified Job Board · desktop full page | `screenshots/list-desktop.png` |
| Lane filtered (Waiting Approval) | `screenshots/list-lane-filtered.png` |
| RO drill-through | `screenshots/list-drillthrough.png` |
| Unified Job Board · mobile ~390px full page | `screenshots/list-mobile.png` |

## Performance (synthetic)

See `PERF_LIST.json` (120 waiting-approval ROs, list page 1):

- rendered rows: 50 (`PAGE_SIZE`)
- filtered total: 120
- query count: ~38
- elapsed: ~121 ms (test env)

## Focused tests

`tests/Feature/Operations/JobBoardListViewTest.php` + Job Board cases in `WorkboardTriageTest.php` — **27 passing**.

## Still out of scope

No merge, staging/production deploy, SaaS pin, ARK Sites, booking, billing, or customer production data changes. PR stays **draft**.
