# Approved scope coverage

How ARK decides whether labor and parts on a repair order are covered by the customer's approval, what that blocks, and where it is going.

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

Editing an existing line keeps its id, so it stays covered by the approval that included it, even when its price or quantity changes. Coverage does not record the approved amount per line. The approved total on the approval event does.

## Intended reauthorization design (not built)

The goal is to make changed and added work visible and easy to get approved, without stopping the shop.

1. **Record what was approved, per line.** An approval stores each covered line's quantity, price, and total, not only its id. Coverage then compares the current line to that record.
2. **Classify the difference.** Each labor and part line on an Approved concern is Approved (unchanged), Changed (amount or quantity differs from the approved record), or Added (not in the approval). Changed and Added lines are the pending reauthorization, with the dollar difference.
3. **Ask for the difference only.** A supplemental approval (portal, phone, in person) covers exactly the pending lines and amounts. It appends a new approval record and scope; it never rewrites the earlier one. No Draft-then-Approved round trip.
4. **Gate where the shop chooses.** A shop setting decides which steps require the pending difference to be approved: none, final invoice, or closeout. Install and completion do not block by default.
5. **Show it everywhere.** The worksheet, technician view, and invoice show pending lines and the pending amount, and the timeline records each supplemental approval.

Until then, the temporary policy above applies.
