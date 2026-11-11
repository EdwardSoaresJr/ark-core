# RO #20 `closed_at` investigation

**Staging pins:** Core `e9b4268` · SaaS `c60f2c37` · tenant `alpha`

## Observed state

| Field | Value |
| --- | --- |
| Status | `ready_pickup` (not terminal) |
| Payment | `unpaid` |
| Invoice id 7 | issued · 25950¢ |
| Ledger payments | 0 |
| `closed_at` | `2026-11-10 23:40:30` |
| `posted_at` / `paid_at` / `close_variant_key` | null |
| `canClose` | false (balance due) |
| `isTerminal()` | false |

## When / which action

Operational event `#242` at `2026-11-10 23:40:32`:

`repair_order_lifecycle_changed` · `in_progress` → `ready_pickup`

`RepairOrderLifecycleTransition` calls `RepairOrderOperationalDates::applyClose()` whenever the destination is `ready_pickup` **or** a terminal status. `applyClose` stamps `closed_at` once if null.

Timestamp matches that transition (within ~2s of the event).

## Intended vs incorrect

**Intended operational close date**, not a paid/financial terminal close.

Evidence in code:

- `RepairOrderOperationalDates::receivesCloseDate()` includes `ReadyPickup`.
- `RepairOrder::displayClosedAt()` returns `closed_at` for ready_pickup.
- Lifecycle projection tests explicitly say work-completed uses ready_pickup transition, not “financial closed”.
- Edit guards / payment / living-invoice refresh key off `isTerminal()`, **not** `closed_at`.
- Reports’ “completed” date scope includes `ready_pickup` and uses `closed_at` as the work-ready timestamp.

Unpaid balance at ready_pickup with `closed_at` set is allowed. Final paid closeout still requires `canClose` (zero balance + issued invoice + close variant).

## Terminal financial / document locks on RO #20?

| Lock | Present? |
| --- | --- |
| Terminal status | No |
| Posted sales (`posted_at`) | No |
| Paid close variant | No |
| Issued invoice | Yes (normal ready_pickup invoice) |
| Customer-presented freeze | No (`wasPresentedToCustomer` false) |
| Edit blocked by terminal | No (`ensureOpenForEditing` allows) |

## Fix proposal

**None for lifecycle semantics.** Do not clear `closed_at` on RO #20.

Optional later (owner review only, out of this follow-up): rename/UI copy that distinguishes “work ready (`closed_at`)” from “financially closed (`status=closed`)” if advisors confuse the column. No production behavior change required for G0.
