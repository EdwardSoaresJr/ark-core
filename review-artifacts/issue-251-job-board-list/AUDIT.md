# Issue #251 — Job Board list view audit

## Current placement (before this change)

| Surface | Route / entry | Role |
| --- | --- | --- |
| Advisor Job Board (home) | `operations.index` → `OperationsHomeController` | Primary shop floor board; kanban lanes from `JobBoardLaneCatalog` |
| Workboard triage | `operations.workboard` | Older swimlane triage (not replaced) |
| Repair Orders index | `operations.repair-orders.index` | Historical/search inventory; not the live board |
| Lane settings | Shop Settings → Job Board lanes | Shop-configurable lane labels/order/colors |

## Lane semantics (preserved)

Default active lanes (shop-configurable; not hardcoded mockup labels):

1. Estimates
2. Waiting Approval
3. Waiting Parts
4. Work in Progress
5. Completed

Mockup labels such as “In Progress · Ready for Pickup” map onto these ARK lanes / RO status catalog entries. This workstream does **not** invent new lifecycle statuses.

Advisor attention zones are separate from lane lifecycle and stay out of the list tabs.

## Board vs list

| | Board (default) | List (`?view=list`) |
| --- | --- | --- |
| Layout | Existing kanban columns | Dense table + mobile stacked rows |
| Cap | 12 cards per lane (`HOME_BOARD_VISIBLE_CARD_LIMIT`) | Uncapped rows for the same triage queue |
| Tabs | All + lane tabs filter columns | All + lane tabs filter rows |
| Counts | Lane `totalCount` | Same `totalCount` source |
| Preference | Cookie `ark_job_board_layout` | Same cookie; Board remains default until product acceptance |

## Row model

One row per repair order. Concern/job text comes from the existing home-card concern summary. Drill-through opens the existing RO workspace (`#builder`). No duplicate workflow state and no destructive quick actions on the row.
