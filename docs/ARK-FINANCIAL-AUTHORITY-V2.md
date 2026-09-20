# ARK Financial Authority v2

**Status:** Frozen · 2026-08-03 · **F1 implementing · RED for F2+**

**Adoption ladder:** [`ARK-FINANCIAL-AUTHORITY-V2-ADOPTION.md`](ARK-FINANCIAL-AUTHORITY-V2-ADOPTION.md)

| Milestone | Status |
| --- | --- |
| **F0** Financial Authority v2 | 🔒 Frozen ✅ |
| **F1** Financial Position | 🟡 Implementing |
| **F2** Issue Final Invoice | ⏸️ After F1 |
| **F3** Portal document switch | ⏸️ After F2 |
| **F4** Advisor closeout | ⏸️ After F2–F3 |
| **F5** Legacy early-invoice compatibility | ⏸️ After F2 |
| **F6** Retire living-invoice compatibility | ⏸️ After F5 |
| **F7** Coverage / multi-payor | ⏸️ After F1 trusted |
| **F8** Financial Timeline | ⏸️ Later |
| **F9** Credit memos / supplemental | ⏸️ Later |

**Supersedes:** Living-invoice / invoice-refresh doctrine in [`ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md`](ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md)  
**Companions:** [`app/Ark/Operations/Financial/README.md`](../app/Ark/Operations/Financial/README.md) · Projection Rule · Authority Answers Directly

This document is the **target architecture**. Existing early-invoice and living-invoice sync behavior is **transitional compatibility**, not the destination.

It sits with Inspection, Maintenance, Evidence, and Customer Recognition: **authority instead of synchronized documents**. It is not a feature — it changes how the system thinks.

**Architectural stop (binding):** Financial Authority is frozen. No invoice work, refresh work, synchronization work, or early-invoice enhancements are permitted until F1 (Financial Position) is implemented. Existing living-invoice behavior exists only as transitional compatibility.

### Governed PR gate

Any proposal or PR that touches estimates, invoices, payments, deposits, coverage, or customer balances must answer first:

> **Does this belong to F1 (Financial Position), or is it blocked by Financial Authority v2?**

| Answer | Action |
| --- | --- |
| F1 Financial Position | May proceed |
| Invoice refresh / sync / early invoice / living invoice | **Does not proceed** |
| Invoice UI redesign, invoice generation changes, deposit-driven invoice behavior, patches that bypass Financial Position | **Blocked** |

Until F1 is intentionally resumed: leave financials alone; focus on shop daily workflow or customer experience.

### Class of bugs eliminated

These questions exist only if an invoice is allowed to be living. Under v2 they disappear:

- Why doesn’t the invoice match the estimate?
- Should Refresh Invoice be automatic?
- What if the advisor forgets?
- Do deposits freeze the invoice?
- When should the invoice update?

**There is no living invoice.** The problem space collapses.

---

## Philosophy (one line)

**The Estimate answers “What has been approved?” The Ledger answers “What money has moved?” The Invoice answers “What was finally billed?”**

Everything else — portal balances, advisor totals, customer responsibility, warranty portions — is a **projection** composed from those authorities.

---

## Three authorities

| Authority | Job | Lifetime |
| --- | --- | --- |
| **Estimate** | Living financial contract (approved work) | Evolves with approval until Final Invoice |
| **Ledger** | Money movement | Append-only forever |
| **Invoice** | Historical evidence that a contract was finalized | Issued once; immutable |

Nothing else is financial authority.

```
Estimate  →  living contract
Ledger    →  money movement
Invoice   →  historical snapshot
```

---

## Principles

### #1 — The Estimate is the living financial contract

Until a repair is complete and closeout readiness is met, there is **one** financial truth: the approved Estimate.

There is never a “living invoice.”

Approval — portal, phone, or in-person recorded — is the only gate that changes the contract. Advisor “refresh” is not a gate.

### #2 — The Invoice is evidence

Like InspectionEvidence and MaintenanceServiceEvent, the Invoice is **historical evidence** that a financial contract was finalized.

It is not the contract itself.

### #3 — Money belongs to the Ledger

Money never belongs to an Estimate.  
Money never belongs to an Invoice.  
Money belongs to the Ledger:

- Deposit
- Payment
- Refund
- Credit
- Write-off

Deposits are **not** invoice payments. They are ledger transactions that reserve work toward whatever the contract ultimately becomes. When the Final Invoice is issued, deposits apply automatically.

### #4 — The Invoice is the consequence of closeout, not a prerequisite for closeout

```
Repair Complete
        │
        ▼
Closeout Readiness
        │
        ├── All approved work finalized
        ├── Labor complete
        ├── Parts complete
        ├── Coverage finalized
        ├── Financial Position resolved
        ▼
Issue Final Invoice
        │
Apply Ledger
        ▼
Collect / Release Vehicle
```

ARK always thinks:

> We issue the invoice **because** the job is complete.

Never:

> Issue an invoice so we can collect payment.

That prevents recreating early invoices as a payment unlock.

---

## Hard prohibition (Never)

**Never allow two living financial contracts for the same Repair Order.**

| Allowed | Forbidden |
| --- | --- |
| Estimate = living · Invoice = historical | Living Estimate **and** Living Invoice |

That sentence is a guardrail for every future financial feature.

---

## Vocabulary (binding)

| Say | Never say (product / new code) |
| --- | --- |
| **Issue Final Invoice** | Issue Invoice (alone), early invoice, refresh invoice, living invoice |
| **Estimate** | Living invoice |
| **Ledger deposit** | Invoice deposit / payment against unfinished invoice |
| **Financial Position** | Ad-hoc estimate vs invoice vs deposit math in UI |

There is one final invoice — not an early invoice and a later invoice.

---

## Flow

```
Estimate
   │
   │  approved changes (approval gated)
   ▼
Living Estimate
   │
   │  customer deposits
   ▼
Ledger (unapplied until Final Invoice)
   │
   │  closeout readiness
   ▼
Issue Final Invoice   ← once
   │
Apply Ledger
   │
Collect remaining balance
   │
Closed
```

After Final Invoice, corrections are **not** refreshes. They are:

- Revised invoice, or
- Supplemental invoice, or
- Credit memo

…per accounting rules decided later. Never silent mutation of the issued Final Invoice.

---

## Financial Position projection

**Not authority.** The projection every operational screen should consume.

Advisors do not ask four questions. They ask one:

> What does this customer owe **today**?

```
Approved Work
− Coverage (warranty / fleet / insurance / goodwill — when that authority exists)
− Deposits
− Credits
− Payments
= Customer Owes Today
```

| Before Final Invoice | After Final Invoice |
| --- | --- |
| Projects from Estimate + Ledger (+ Coverage) | Projects from Invoice snapshot + Ledger |

**Forbidden:** Blade, Alpine, presenters, or portal deriving owe-today from raw estimate total, invoice total, or deposit balance alone.

North-star question remains: *What does this customer owe us right now?*  
**Answer path:** Financial Position projection only.

---

## One question per financial surface (frozen invariant)

**Every financial screen must answer exactly one question.**

Same discipline as Inspection, Evidence, Maintenance, and Customer Recognition — surfaces do not become overlapping financial dashboards.

| Surface | Primary question |
| --- | --- |
| **Estimate** | What has the customer approved? |
| **Financial Position** | What does the customer owe if we finished now? |
| **Ledger** | What money has moved? |
| **Issue Final Invoice** | Is this repair ready to become a financial record? |
| **Final Invoice** | What was billed? |
| **Receipt** | What was paid? |

If a screen starts answering a second question, split it or move the second answer to the surface that owns that question.

---

## Customer surfaces

| Phase | Customer sees |
| --- | --- |
| **Before completion** | Estimate (always current) · Deposit received · Balance if completed today (Financial Position) |
| **After Issue Final Invoice** | Final Invoice · Paid · Balance · Receipt |

Customers must not wonder why two financial documents exist while work is still evolving.

---

## PDFs and communication artifacts

| Artifact | Rule |
| --- | --- |
| **Estimate PDF** | Living document versions; older PDFs may be superseded |
| **Final Invoice PDF** | Issued once with the Final Invoice; **immutable**; never regenerated as “refresh” |
| Emailed / printed copies | Historical communication evidence — may be superseded by a later estimate version or by the Final Invoice, but do not create a second living invoice |

History is preserved. Money stays correct because the Estimate (then the Final Invoice) is synchronized by **approval and closeout**, not advisor memory.

---

## Advisor UI posture

**Before Final Invoice** — the invoice literally does not exist yet:

```
Estimate
──────────────
Approved Work
Coverage
Customer Responsibility
Deposits Received
Projected Balance          ← Financial Position

[Issue Final Invoice]      ← enabled only when Closeout Ready
```

Missing on purpose: Invoice tab · Refresh Invoice · Sync Status.

That matches how advisors think through a repair — contract and money first; Final Invoice only when the job is done.

**After Final Invoice**

```
Financial
  Final Invoice
  Receipt
  Payments
  Ledger
```

---

## Coverage (future — do not invent early)

Warranty / fleet / insurance / internal / goodwill belong to a future **Coverage** authority.

Invoice and Financial Position **consume** Coverage. They do not become multi-payor systems themselves.

Until Coverage exists, human process may note customer portion on deposits — that is not line-level billing truth.

---

## Implementation freeze (binding)

**Hard gate:** Do not touch invoice logic again until **Financial Position** exists and every “owe today” surface consumes it.

**Suspend further development of:**

- Invoice synchronization / living snapshot refresh
- Advisor “Refresh Invoice” as a product task
- Early-invoice features that treat invoice as a mid-job living contract
- Final-Invoice-at-closeout migration **before** Financial Position

| Existing behavior | Classification |
| --- | --- |
| Early issued invoices on open ROs | Transitional compatibility |
| `RefreshLivingInvoiceSnapshotAction` | Transitional — do not extend |
| `RefreshCustomerInvoiceAction` / Refresh Invoice UI | Transitional — do not extend |
| Deposit-tolerant living sync (2026-08-03) | Transitional patch; not target architecture |

**Allowed now:** money-loss bugfixes that do not expand living-invoice doctrine; doctrine; **Financial Position** design and implementation.

**Not allowed:** refining the old two-living-documents model; invoice code ahead of Financial Position.

---

## Relationship to v1 closeout doc

[`ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md`](ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md) remains useful for:

- Ledger types and BalanceDueCalculator formula shape
- Invoice operational states after issuance
- Overpay → store credit, voids, write-offs
- Explicit (not status-side-effect) Final Invoice issuance

It is **wrong** where it allows a living invoice snapshot after issuance until settlement, or advisor refresh as the sync gate.

**v2 wins** on Estimate / Ledger / Invoice roles, Principle #4, Issue Final Invoice naming, Financial Position, and the Never rule.

---

## Adoption sequence (when implementation resumes)

Financial Authority stays **RED** until F1 is complete.

| Milestone | Status | Deliverable |
| --- | --- | --- |
| **F0** Doctrine freeze | 🔒 **Done** | This document |
| **F1** Financial Position | ⏸️ **Next — sole resume point** | Projection + every owe-today surface consumes it |
| **F2** Advisor financial UI | ⏸️ After F1 | Estimate · Coverage · Deposits · Projected Balance · Issue Final Invoice (Closeout Ready only); no Invoice tab |
| **F3** Issue Final Invoice event | ⏸️ After F1–F2 | Closeout readiness → immutable Final Invoice once |
| **F4** Migrate open early invoices | ⏸️ After F3 | Compatibility without rewriting closed history |
| **F5** Retire living-invoice refresh | ⏸️ After F4 | Delete sync/refresh transitional code |
| **F6** Coverage authority | ⏸️ After F1 trusted on floor | Multi-payor consumed by Financial Position |

Do not reverse this order. **F1 before any invoice logic.** Financial Authority remains red until F1 ships.
