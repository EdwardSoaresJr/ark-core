# Approved scope coverage

How ARK decides whether labor and parts on a repair order are covered by the customer's approval, what that blocks, and where it is going.

> **Approvals recorded before amounts were kept ([#66](https://github.com/EdwardSoaresJr/ark-core/issues/66)).** A scope recorded before per-line amounts were stored has `line_amounts` null, and older approvals may have no scope at all. ARK does not infer what those approvals were worth and does not rewrite them. Instead, the first time staff raise the quantity or price of a line on such a concern, `LegacyApprovalCeiling` saves the line's amounts from just before the raise in `repair_order_lines.legacy_approved_amounts`. That is the most ARK had counted as approved for the line, so it becomes the line's ceiling: the increase is pending until a recorded approval stores real amounts. Lowering a line, editing its text, and moving the job forward record nothing and block nothing.

## What coverage is

When a concern is approved (by staff or the customer portal), `RecordApprovedWorkScopeAction` reprices the repair order and stores an `ApprovedWorkScope`: the labor and part line ids on that concern at that moment (`line_ids`), and every non-note line's quantity, unit price, subtotal, standing discount, shop fee, tax, and total (`line_amounts`). A line is covered when its concern is Approved and it is in the concern's latest scope, or when an `AuthorizationException` names it. `WorkCompletionAuthorization` owns this check.

Coverage drives:

- **Approved dollars.** `EstimateTotalsCalculator` counts only covered labor and parts as approved sales and invoice basis. Each approved line counts at the lesser of its current amounts and the amounts its concern's latest scope recorded: a decrease or deletion counts as it is, and an increase stays at the approved amounts, tax and fees included. The invoice lists such a line at its approved quantity, price, and amounts.
- **Pending increases.** `pendingAuthorizationIncreases()` lists approved lines whose current total is above the recorded total, with the difference. The worksheet marks each "+$X needs authorization", and the totals panel adds the difference to "Needs authorization". Editing, installing, completing, and finishing are not blocked by it.
- **Identification.** `linesRequiringAuthorization()` lists labor and parts on Approved concerns that the approval does not cover. The worksheet marks them "Needs authorization", and the totals panel lists them as "On the estimate. Not in approved sales until the customer approves these lines." `UnresolvedAuthorizationReport` lists installed parts and completed labor without coverage.
- **Gates.** `installBlockedReason()`, `completionBlockedReason()`, and `lifecycleBlockedReason()` can refuse installing a part, completing a concern, and moving to quality check, completed, invoiced, or ready for pickup.

## Temporary policy (current)

Labor or a part added to an Approved concern after the approval does not block install, completion, or a finished status. The single rule is `WorkCompletionAuthorization::blocksWork()`.

Before this, the gates refused that work until it was covered. Staff moved the concern back to Draft and then to Approved, which recorded a new scope covering every current line. That workaround silently extended the customer's approval to work the customer had not seen.

Unchanged:

- Added work is not covered. It stays out of approved dollars and the invoice basis, keeps its "Needs authorization" mark, and appears in the unresolved report until the customer approves it or an exception covers it.
- Approval records, approved scopes, approved amounts, signatures, exceptions, revocations, and their history are not rewritten or extended.
- Work on a concern the customer has not approved (Draft, Recommended, Deferred, Declined) still needs a customer approval or an exception before a part is installed, labor is completed, or the repair order is finished.
- A concern with an approval or exception on file still cannot be deleted.
- Staff permissions and estimate-version concurrency checks are unchanged.

Editing an existing line keeps its id, so it stays covered by the approval that included it. If its price or quantity rises, only the approved amounts count; the difference is pending until a new approval of the concern records the new amounts.

**Record approval** brings pending work in. Besides recommended work on an estimate with no approval yet, it re-scopes every Approved concern that has uncovered lines or a pending increase (`approvedConcernIdsAwaitingAuthorization()`). Its default amount is the approved total plus `pendingAuthorizationCents()`. Each re-scope appends a scope with current amounts; earlier scopes and approval events are not changed. A job already past approval (Waiting Parts through Ready Pickup) keeps its status.

The final invoice lists only lines its total bills: labor and parts the approval does not cover are left off the invoice, as they are left out of its total. The repair-order-level amount on a customer approval event (`approval_events.approved_amount_cents`) is preserved.

When the shop fee has a cap, raising one line moves part of the capped fee off the other lines. The raised line still counts at its recorded fee and the others at their lower current fee, so approved dollars can come out slightly below the original approval, never above it. The moved fee is part of the pending increase.

Sublet, fee, and package lines on an Approved concern are not part of coverage. They count as approved at no more than their recorded amounts; one added after the approval has no recorded amounts and counts at its current amount, as before.

## Intended reauthorization design (step 1 built)

The goal is to make changed and added work visible and easy to get approved, without stopping the shop.

1. **Record what was approved, per line.** Built for #66: an approval stores each line's amounts, and approved dollars count the lesser of the current and approved amounts.
2. **Classify the difference.** Each labor and part line on an Approved concern is Approved (unchanged), Changed (amount or quantity differs from the approved record), or Added (not in the approval). Changed and Added lines are the pending reauthorization, with the dollar difference.
3. **Ask for the difference only.** A supplemental approval (portal, phone, in person) covers exactly the pending lines and amounts. It appends a new approval record and scope; it never rewrites the earlier one. No Draft-then-Approved round trip.
4. **Gate where the shop chooses.** A shop setting decides which steps require the pending difference to be approved: none, final invoice, or closeout. Install and completion do not block by default.
5. **Show it everywhere.** The worksheet, technician view, and invoice show pending lines and the pending amount, and the timeline records each supplemental approval.

Until then, the temporary policy above applies.
