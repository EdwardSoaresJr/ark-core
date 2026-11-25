# RO Financial & Lifecycle Overhaul — Findings

**Baseline:** Core `c379a2c818a37b2a76eae2c111dbfe08a42aeb44` · SaaS `2f513445c1afa7c9156ed5d6dadd116810dcc0ba`  
**Date:** 2026-11-25

## Root causes (deposit / payment)

### F1 — Suggested deposit was a hard ceiling on the first capture (P0)

`RepairOrderDepositRecordingGuard::remainingAllowedDepositCents()` returned the remaining **suggested** shop-policy amount whenever it was > 0, instead of the remaining authorized owe-today.

Effect: on an approved $2,500 RO with a $500 suggested deposit, cash/card/terminal/portal could not accept $2,500 (or any amount above the suggested remainder) until the suggested amount was satisfied in a prior step. Advisors experienced this as “ARK refuses the full payment” or “Take payment won’t take the balance.”

Invariant to preserve: never collect more than authorized owe-today (or, when nothing is authorized yet, the suggested advance-held remainder for recommended parts/diagnostics).

### F2 — Final invoice gated only on Ready for Pickup (P1)

`GenerateInvoiceSnapshotAction` and `RepairOrderFinancialPresenter::canGenerateInvoice` require `ready_pickup`. Closeout already allows `completed` / `invoiced` / `ready_pickup`. Staff had to flip status solely to unlock invoicing even when work was complete.

Ready Pickup still auto-issues on transition; that remains the primary happy path.

### F3 — Invoice / payment blocking reasons hidden (P1 UX)

`invoiceBlockingReason` returned `null` whenever status was not Ready Pickup, so the rail gave no explanation for a missing Generate Invoice / Take Payment control.

### F4 — Financial workflow posture ignored issued invoices off Ready Pickup (P1)

`workflowPosture()` treated any non–Ready Pickup status as pre-invoice even when an invoice document existed (e.g. status left at Completed/Invoiced). Misleading rail copy.

### F5 — Pre-authorization advances (policy)

Deposits before approval are already the ledger Deposit type (unapplied until invoice). Suggested policy already includes recommended parts/diagnostics. After F1, when owe-today is 0, suggested remainder still authorizes that advance-held amount without implying approval. No second ledger invented.

Full free-form pre-approval cash without suggested/estimate anchors remains out of scope (needs explicit shop policy + legal treatment).

## Guards preserved

| Guard | Why kept |
|---|---|
| Payment recorder requires issued invoice | Settlement vs deposit classification |
| Deposit recorder refuses terminal ROs | No money on closed tickets via RO path |
| Capture fails do not post ledger | No speculative money |
| Idempotent financial submission intents | Duplicate submit safety |
| Invoice refresh blocked after write-off/refund/credit | Invoice integrity |
| Close-Paid blocked while balance due | AR / paid closeout |
| Authorization caps billing | No unapproved charges on invoice |
| Deposit cap at owe-today (or suggested advance when none authorized) | No over-collection beyond authority |

## Concern / RO lifecycle (findings)

- Concern `production_status` is independent; Job Board lanes follow **RO status only**.
- Dual Waiting Parts (RO vs concern) can disagree; board follows RO.
- Parts clear must not invent active bay work. Inventory receipt clears `waiting_parts` → `pending`; technicians resume explicitly. (PR #274 briefly cleared to `in_progress`; final corrections restore conservative Pending.)
- `invoiced` and `ready_for_work` are manually selectable but largely duplicative (invoice document / Approved+In Progress). Safe to hide from forward menus without deleting enum values.
- Approved → Ready for Work → In Progress is already skippable (`approved → in_progress`).

## Blocked for later migration

- Collapsing the 12-value persisted RO enum into a 5-stage model (requires historical compatibility project).
- Deriving Job Board Parts lane solely from concern/parts pressure (lane remap).
- Free-form pre-approval deposits with no estimate/suggested anchor.
