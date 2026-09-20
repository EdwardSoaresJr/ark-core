# Tekmetric Operational Lifecycles

## Core Queue Model

Tekmetric separates broad workflow columns from more granular operational statuses.

- Columns organize the main queue: Estimates, Work-In-Progress, Completed.
- RO labels add shop-specific detail inside each column.
- Workflow statuses are dynamic and can move ROs between columns based on approval, work, completion, and payment state.
- Quick Actions allow status changes directly from the queue, avoiding unnecessary navigation into the estimate.

## Status Language

Important status names:

- Requires authorization: jobs exist but have not been sent for customer authorization.
- Pending: estimate has been sent by email, text, or direct link and is awaiting authorization.
- Declined: all estimate jobs were declined.
- Not started: estimate is approved, but the technician has not started the job clock.
- X of Y hours: technician has started work and has completed some labor/sublet quantity.
- Balance due: work is complete, but customer has not paid.
- Ready to post: payment is complete, but the RO has not been posted.
- Credit due: overpayment/refund state.
- In A/R: posted to accounts receivable.

ARK implication: statuses should name operational pressure directly. Avoid generic labels like `open`, `done`, or `processing` when the shop actually needs to know authorization, parts, work, payment, or posting pressure.

## Estimate Lifecycle

Tekmetric estimate flow:

1. Customer/technician concerns are captured on the RO.
2. Advisor builds jobs from concerns using canned jobs, Smart Jobs, labor guide, parts lookup, or blank jobs.
3. Jobs group labor and related parts so the estimate tells a coherent service story.
4. Estimate is shared for authorization.
5. Customer approves or declines jobs.
6. Approval transitions the RO into Work-In-Progress.
7. Authorization history stores point-in-time approval records.

Operational pattern: estimate is not a static document. It is the bridge between discovery, customer trust, approval, production, and financial control.

## Declined And Deferred Work

Tekmetric job history includes approved jobs, declined jobs, draft jobs, and saved-for-later jobs. Declined work remains valuable operational memory and can be surfaced later in customer or vehicle history.

ARK implication: declined work should not disappear. It should remain part of service history, future estimate building, and customer follow-up.

## Parts Lifecycle

Parts orders are tracked separately but remain tied to RO workflow.

- Order statuses include Ordered, Received, and Partially Received.
- Parts can be marked received from Orders, Estimate tab, or Summary tab.
- Partial receipts are explicitly modeled.
- Parts returns move through refund-pending and refund-complete states.

ARK implication: parts state should be visible as operational pressure, especially when work is waiting on ordered or partially received parts.
