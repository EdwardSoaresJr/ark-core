# Issue #251 — acceptance matrix

Stop for Carlos/owner visual review. No merge. No deploy. No SaaS pin.

## Commit

Recorded on PR open (see PR body for exact SHA).

## Screenshots

| Viewport | Path |
| --- | --- |
| List · desktop | `screenshots/list-desktop.png` |
| List · mobile (~390px) | `screenshots/list-mobile.png` |
| Board · desktop (preserved) | `screenshots/board-desktop.png` |

## Acceptance

| Criterion | Result | Evidence |
| --- | --- | --- |
| Audit current Job Board / lanes / counts | Pass | `AUDIT.md` |
| Functional tabbed list (not static mockup) | Pass | List view at `/app?view=list`; screenshots |
| Board/List toggle; Board remains default | Pass | Cookie `ark_job_board_layout`; default board |
| Tabs from shop Job Board lanes + All | Pass | Estimates / Waiting Approval / Waiting Parts / Work in Progress / Completed |
| Counts match lane totals / filtered rows | Pass | Same `totalCount` as board columns; Alpine filter counts on list |
| Columns: RO #, Customer, Vehicle, Concern, Status, Tech, Promised, Parts, Priority, Updated | Pass | `home-board-list*.blade.php` |
| One row per RO; opens existing RO workspace | Pass | Row links to `$card->href#builder` |
| Search / employee filter / sort / empty states | Pass | Alpine filters + sortable headers |
| Responsive desktop table + mobile list | Pass | `d-md-*` table / mobile cards; screenshots |
| No N+1 beyond existing board projection | Pass | Reuses triage query + uncapped card pass for list only |
| Closed/terminal ROs stay off the board | Pass | `JobBoardListViewTest` |
| Board UI preserved | Pass | Board screenshot + `WorkboardTriageTest` |
| Tenant/auth isolation | Pass | Forbidden without ops access |
| Focused tests | Pass | `JobBoardListViewTest` (6) + Workboard triage / lane catalog (32 total) |
| No ARK Sites / billing / booking / portal changes | Pass | Core-only branch `feat/job-board-list-view` |

## Lane ↔ mockup mapping

Mockup labels are illustrative. ARK keeps shop-configurable lanes:

| Mockup example | ARK lane |
| --- | --- |
| All | All |
| In Progress | Work in Progress |
| Waiting for Parts | Waiting Parts |
| Waiting for Approval | Waiting Approval |
| Ready for Pickup / Completed | Completed (includes ready-for-pickup statuses) |

## SaaS integration note

After owner visual accept and Core merge: pin SaaS to the merged Core SHA. No pin in this PR.

## Local review

1. Open `/app` (Board default) and `/app?view=list`.
2. Confirm tabs, table density, drill-through, and Board toggle.
3. Narrow viewport for mobile cards.
