# RO Index — Owner UI Proposal (2026-11-23)

**Status:** Owner-proposed design direction, for Cursor review. **Not implemented or approved for deployment.**
**Scope:** ARK Core repair-order index / Job Board list view, including PR #252 review. This document is not a request to merge PR #252 or change the Sites stack freeze.

## Reason

The current 1366px list has columns RO #, Customer, Vehicle, Concern / Job, Status, Tech, Promised, Parts, Total, Attention, Activity, and Updated. The owner does not use the Promised field in the shop's daily workflow. The Parts column repeats procurement detail that belongs in the RO and competes with useful at-a-glance information. A fixed Parts column also duplicates the existing Waiting Parts status.

## Proposed compact index

| Column | Behavior |
| --- | --- |
| RO # | Keep; opens repair order. |
| Customer / Vehicle | Combine into one two-line cell, with existing links/details preserved, where feasible. |
| Concern / Job | Keep short scannable summary; truncate gracefully. |
| Status | Keep the current workflow state, including Waiting Approval, Waiting Parts, In Progress, Ready for Pickup. |
| Tech | Keep assigned technician. |
| Total | Keep amount and existing authorization/billing semantics. |
| Attention | Keep only if the signal is actionable and accurately derived. Avoid defaulting to an uninformative “Watch” label. |

**Remove from the list presentation:** `Promised` and `Parts` as always-visible standalone columns.

**Activity and Updated:** Existing activity indicator/drill-through and recency information have operational value. Do not silently remove or regress either as part of this proposal. Review whether they can be retained in compact form or integrated with row metadata; preserve their functionality and test coverage. The small concept table discussed with the owner omitted these for simplicity, **not** as an authorization to delete their behavior.

## Semantics

- **Status = where the RO is in the workflow.** `Waiting Parts` means the RO is blocked waiting for parts; it is not a synonym for any estimate with part lines.
- **Attention = what requires someone to act.** Examples, only where supported by real data: `Approval needed`, `Parts needed`, `Parts on order`. No guessed or fabricated attention status.
- **Parts needed** means identification/sourcing/ordering is outstanding; an RO can remain `In Progress` while requiring action on parts.
- **Parts on order / Waiting Parts** should not be conflated. Only surface a procurement state supported by persisted data.
- **Parts ready** may be valuable later, but do not introduce new logic or a new data model in this small index cleanup.
- Avoid a second permanent parts column or duplicating the status badge in Attention.

## Boundaries

1. This is **presentation work only** for Promised and Parts: keep fields, relationships, history, and compatibility for shops using them. No schema/data migration or destructive removal.
2. Keep promised date/time available where already supported in RO detail; no new completion estimate or promise workflow in this slice.
3. Preserve existing status logic, authorization totals, filters, tab counts, sorting, links, keyboard access, activity actions, and updated timestamps.
4. Maintain readable 1366x900, 1920x1080, and 390x844 layouts, with no new horizontal overflow or cramped mobile row.
5. This proposal does **not** authorize production or staging deployment, PR merges, release-freeze changes, new automated status inference, or changes to LugsNPlugs data.

## Cursor acceptance checklist (after approval to implement)

- No visible `Promised` or `Parts` table headers/cells in the RO index desktop/mobile list.
- Customer and vehicle are legible together; all existing navigation targets still work.
- Waiting Parts continues to be represented accurately by the Status badge; a part line alone does not create a false `Parts needed` Attention entry.
- Attention wording is actionable only where grounded; ambiguous default `Watch` behavior is documented and proposed separately rather than silently rewritten in this narrow slice.
- Activity and Updated remain accessible with no lost behavior.
- No schema or billing/authorization changes.
- Screenshot evidence at 1366x900, 1920x1080, and 390x844; targeted regression tests pass.

## Sample conceptual rows

| RO # | Customer / Vehicle | Concern / Job | Status | Tech | Total | Attention |
| --- | --- | --- | --- | --- | ---: | --- |
| #89 | SYN Brakes Demo / 2019 Honda CR-V | Grinding front brakes | Waiting Approval | Alex Rivera | $425 | Approval needed (only if backed by state) |
| #90 | SYN Parts Hold / 2021 Toyota Tacoma | Timing cover leak | Waiting Parts | Jordan Lee | $1,066 | Parts on order (only if backed by state) |
| #91 | SYN Wip Bay / 2018 Ford F-150 | Serpentine belt and tensioner | In Progress | Alex Rivera | $505 | Parts needed (only if backed by state) |
| #92 | SYN Ready Pickup / 2020 Subaru Outback | Four-wheel alignment | Ready for Pickup | Jordan Lee | $217 | None, unless action is genuinely due |

*These rows illustrate layout and wording, not verified live backend states.*

## Implementation sequencing

First, make the narrow column removal and layout consolidation in a reviewable UI PR. Separately inspect the Attention classifier and owner-review any revised business rules; do not smuggle a new procurement or status system into a visual cleanup.
