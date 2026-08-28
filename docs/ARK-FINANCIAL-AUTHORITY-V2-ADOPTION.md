# Financial Authority v2 — Adoption Ladder

**Status:** Approved · F1 implementing  
**Canonical doctrine:** [`ARK-FINANCIAL-AUTHORITY-V2.md`](ARK-FINANCIAL-AUTHORITY-V2.md)

This document is the **implementation adoption ladder**. Doctrine invariants live in V2. This ladder sequences how ARK adopts them without rewriting financial history in one PR.

## Binding implementation constraints

1. Follow `docs/ARK-FINANCIAL-AUTHORITY-V2.md` as the only financial architecture source.
2. Financial Position is the only living financial projection.
3. Invoice is an immutable closeout artifact (F2+).
4. Transitional compatibility (living invoice / Refresh Invoice / early invoices) stays until **F6**.
5. Do not invent financial concepts outside frozen doctrine.

---

## Financial Position — Principle #1

**Financial Position owns nothing. It answers everything.**

Disposable projection. Never persist:

- `projected_balance`
- `customer_responsibility`
- `deposits_total`
- `amount_due`
- `estimate_total_cache`

Every request rebuilds from **Estimate + Ledger + Coverage (later)**.

### Public API rule

Every screen asking “What does the customer owe?” must call:

```php
FinancialPositionProjection::for($repairOrder)
```

No Blade, controller, presenter, API resource, or portal page may compose financial math directly.

**Not allowed outside `FinancialPositionCalculator`:** `EstimateTotalsCalculator`, `BalanceDueCalculator`, Invoice, Ledger (for owe-today orchestration).

### Calculator rule

```
FinancialPositionProjection
        │
        ▼
FinancialPositionCalculator
        │
────────┼────────
Estimate
Ledger
Coverage(0)
Invoice(if exists)
```

### Read-only / GET-pure

Readonly DTO. No setters. Construct once. Throw away. GET never mutates (no refresh, sync, cache writes, timestamp updates).

### Field naming

| Internal | UI |
| --- | --- |
| `customer_owes_today` | Projected Balance |
| `approved_work` | Approved Work |
| `customer_responsibility` | Customer Responsibility |
| `deposits` / `payments` / `credits` | Deposits / Payments / Credits |
| `coverage` | Coverage (0 until F7) |

### Financial Contract Source

`Estimate` | `Invoice` — never `LivingInvoice` / `Compatibility` as business language.

### Legacy rule

Living-invoice compatibility must never leak upward:

Advisor → Financial Position  

**Not** Advisor → Refresh Invoice → Financial Position.

---

## Adoption ladder

| Phase | Deliverable | Status |
| --- | --- | --- |
| **F1** | Financial Position + owe-today consumers | Implementing |
| **F2** | Issue Final Invoice (once, immutable) | Later |
| **F3** | Portal before/after invoice document switch | Later |
| **F4** | Advisor closeout = Position → Issue → Collect → Close | Later |
| **F5** | Legacy early-invoice compatibility mode; no new entries | Later |
| **F6** | Retire Refresh / living sync / drift / freshness | Later |
| **F7** | Multi-payor Coverage | Later |
| **F8** | Financial Timeline (derived) | Later |
| **F9** | Credit memos / supplemental / revised invoices | Later |

---

## Stop gate

When F1 merges: **Stop. Observe.** Financial Authority remains **RED**. Only F2 may reopen Financial.

No opportunistic invoice cleanup, payment cleanup, coverage, portal redesign, reports, or ledger refactor.
