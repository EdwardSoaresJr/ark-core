# Approved scope coverage

How ARK decides whether labor and parts on a repair order are covered by the customer's approval, what that blocks, and where it is going.

> **Approvals recorded before per-line amounts ([#66](https://github.com/EdwardSoaresJr/ark-core/issues/66)).** A scope stores each labor and part line's approved amounts in `approved_work_scopes.approved_lines`. Older approvals have a scope with `approved_lines` null, or no scope at all. ARK does not invent their amounts and does not rewrite them. Instead, the first time staff raise the quantity or price of a line on such a concern, `LegacyApprovalCeiling` saves the line's amounts from just before the raise in `repair_order_lines.legacy_approved_amounts`. That is the most ARK had counted as approved for the line, so it becomes the line's ceiling: the increase is "Needs authorization" and stays out of approved dollars and the invoice until Record approval stores real amounts. Lowering a line, editing its text, and moving the job forward record nothing and block nothing.

## What coverage is

When a concern is approved (by staff or the customer portal), `RecordApprovedWorkScopeAction` stores an `ApprovedWorkScope`: the labor and part line ids on that concern at that moment. A line is covered when its concern is Approved and it is in the concern's latest scope, or when an `AuthorizationException` names it. `WorkCompletionAuthorization` owns this check.

Coverage drives:

- **Approved dollars.** `EstimateTotalsCalculator` counts only covered labor and parts as approved sales and invoice basis.
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

Editing an existing line keeps its id, so the work stays covered and does not block. When its price or quantity rises above the scope's approved subtotal, approved dollars and the invoice count what was approved. Each entry stores the line's quantity, unit price, subtotal, standing discount, shop fee, tax, and total at approval, and a raised line is billed at exactly those amounts. Entries recorded with only a subtotal fall back to the approved subtotal, with discount and shop fee in proportion and tax on that base. The increase is listed as "Needs authorization" and on the invoice line as not approved and not billed. A lower amount stays approved. One "Record approval" stores a new scope at the new amounts. `approval_events.approved_amount_cents` stays the cumulative approved total. A line removed after approval is listed as removed for staff and in the customer portal until the next approval re-scopes the concern.

## Intended reauthorization design

Steps 1 and 2 are built, and step 3 is built for staff-recorded approval only. The customer portal cannot yet approve a change to an already-approved concern. Steps 4 and 5 are not built.

The goal is to make changed and added work visible and easy to get approved, without stopping the shop.

1. **Record what was approved, per line.** An approval stores each covered line's quantity, price, and total, not only its id. Coverage then compares the current line to that record, and approved dollars count the lesser of the current and approved amounts. This is the first fix for #66.
2. **Classify the difference.** Each labor and part line on an Approved concern is Approved (unchanged), Changed (amount or quantity differs from the approved record), or Added (not in the approval). Changed and Added lines are the pending reauthorization, with the dollar difference.
3. **Ask for the difference only.** A supplemental approval (portal, phone, in person) covers exactly the pending lines and amounts. It appends a new approval record and scope; it never rewrites the earlier one. No Draft-then-Approved round trip.
4. **Gate where the shop chooses.** A shop setting decides which steps require the pending difference to be approved: none, final invoice, or closeout. Install and completion do not block by default.
5. **Show it everywhere.** The worksheet, technician view, and invoice show pending lines and the pending amount, and the timeline records each supplemental approval.

Until then, the temporary policy above applies.
