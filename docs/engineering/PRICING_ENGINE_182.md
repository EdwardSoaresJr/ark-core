# ARK Pricing Engine — Core #182

**Status:** Slice A (safety) + Slice B (shared context) implemented on the labor-matrix branch.  
**UI:** No Labor Matrix redesign. Billing Classes visual merge remains #180 after this foundation.

---

## Goal

Every repair order knows which pricing rules belong to it. Calculations use those rules. Adjustments can be explained. Shop Settings edits never silently reprice existing ROs.

```text
Shop Pricing Configuration (editable)
        │  stamp at RO create / settings activation
        ▼
Versioned RO profiles (immutable for ordinary settings edits)
  ├─ labor_pricing_profile
  ├─ shop_fee_profile
  └─ commercial_pricing_profile (+ parts_matrices schema 2)
        │
        ▼
RepairOrderPricingContext  ← single resolution entry
        │
        ▼
EstimateTotalsCalculator / LaborAuthority / parts preview / documents
        │
        ▼
Explainable results → approval / invoice
```

---

## Existing authorities (kept, not replaced)

| Component | Role |
|---|---|
| `LaborPricingProfile` | Rates + billing minimums frozen on RO |
| `ShopFeeProfile` | Shop-wide percent/flat fee mode frozen on RO |
| `CommercialPricingProfile` | Class discounts, fee overrides, presentation, matrix keys; schema 2 adds matrix **definitions** |
| `LaborPolicyResolver` / `LaborAuthority` | Labor resolution |
| `EstimateTotalsCalculator` | Line fee/discount/tax/totals |
| `WorkCompletionAuthorization` | Approved-dollar limits |

`RepairOrderPricingContext` does **not** invent a second calculator. It coordinates frozen inputs.

---

## Historical / legacy policy

| RO state | Missing commercial stamp | Behavior |
|---|---|---|
| New RO | N/A | Stamped at create (schema 2) |
| Living, null | Transition | Live settings until next Billing Classes / parts-matrix activation stamps pre-change rules |
| Terminal, null | Unknown history | **No live fallback.** `recalculateRepairOrder` no-ops. Stored line cents remain authoritative. Presentation defaults conservatively. |

We do **not** fabricate Monday’s historical discounts for closed null-profile ROs.

---

## Settings activation

Billing Classes and Parts Matrix saves:

1. `lockForUpdate` on `shop_settings`
2. Stamp living null commercial profiles with current rules (includes matrices)
3. Upgrade schema-1 stamps that lack `parts_matrices` by freezing current matrix definitions
4. Commit new shop configuration
5. **Do not** recalculate living ROs or refresh open estimate snapshots

Explicit customer-class change (`ApplyCustomerBillingClassChangeAction`) restamps from current settings and recalculates; events flag `requires_additional_authorization` when totals rise after approved work. Invoice paths continue to respect approved portions.

---

## Remaining live-settings dependencies (tracked)

| Area | Issue |
|---|---|
| Tax Rules still recalculate living ROs on save | #174 |
| Labor policy tables for brand-new lines when labor profile lacks frozen rates (schema 1 legacy) | #178 follow-ups |
| Full parts matrix editor / calculator UI | #175 |
| Billing Classes UI merge into Labor | #180 |

---

## Provenance

`RepairOrderPricingContext::provenance()` exposes schema/version flags for future advisor explainability. Estimate snapshots now include `commercial_pricing_profile` / `pricing.provenance` and freeze `customer.document_presentation_profile`.

---

## Rule precedence (current accepted order)

1. Line quantity × unit price (labor/parts already resolved)
2. Standing discount (commercial profile / customer class)
3. Shop fee (shop-fee profile + commercial class overrides)
4. Tax (still live shop tax settings — #174)
5. Totals; approved portions clamp invoice/authorization surfaces
