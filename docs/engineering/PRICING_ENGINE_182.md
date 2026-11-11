# ARK Pricing Engine — Core #182 / #183 / #174 safety

**Status:** Shared context (#182) plus parts-matrix lifecycle safety (#183) and forward-only tax stamps (#174, tax-safety slice only — not multi-tax UI).  
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
  ├─ commercial_pricing_profile (+ parts_matrices schema 2)
  └─ tax_profile
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
| `CommercialPricingProfile` | Class discounts, fee overrides, presentation, matrix keys; schema 2 freezes matrix **definitions** |
| `TaxProfile` | Tax enabled/rate/taxable bases frozen on RO |
| `LaborPolicyResolver` / `LaborAuthority` | Labor resolution |
| `EstimateTotalsCalculator` | Line fee/discount/tax/totals |
| `WorkCompletionAuthorization` | Approved-dollar limits |

`RepairOrderPricingContext` does **not** invent a second calculator. It coordinates frozen inputs.

---

## Historical / legacy policy

| RO state | Missing commercial / tax stamp | Behavior |
|---|---|---|
| New RO | N/A | Stamped at create (commercial schema 2 + tax schema 1) |
| Living, null | Transition | Live settings until next matching settings activation stamps pre-change rules |
| Terminal, null | Unknown history | **No live fallback.** `recalculateRepairOrder` no-ops. Stored line cents remain authoritative. |

**Schema-1 commercial limitation:** stamps that only stored matrix *keys* cannot reconstruct pricing tiers that were edited before the schema-2 upgrade path froze definitions. We do **not** invent historical brackets. Living schema-1 ROs receive a one-time freeze of *then-current* matrix definitions on the next matrix/billing-class activation; that is a documented approximation, not reconstructed history.

Archived parts matrices remain in `shop_settings.parts_matrices` with `is_active: false`. Active UI / new-RO stamps only see active matrices. Existing RO stamps retain the definitions they were frozen with.

---

## Settings activation

Billing Classes, Parts Matrix edit/archive, and Tax saves:

1. `lockForUpdate` on `shop_settings`
2. Stamp living null profiles with current rules (commercial / matrices / tax as applicable)
3. Upgrade schema-1 commercial stamps that lack `parts_matrices` by freezing current matrix definitions (see limitation above)
4. Commit new shop configuration (matrix delete = soft-archive)
5. **Do not** recalculate living ROs or refresh open estimate snapshots

Explicit customer-class change (`ApplyCustomerBillingClassChangeAction`) restamps commercial rules from current settings and recalculates; events flag `requires_additional_authorization` when totals rise after approved work. Invoice refresh continues to use approved portions via `WorkCompletionAuthorization` — approved dollars are not silently expanded.

---

## Parts matrix selection on stamped ROs (#183)

- Explicit matrix keys resolve with **exact match only** against the RO stamp.
- A matrix created after the RO was opened is not on the stamp → visible 422, never live shop fallback.
- Concern default matrices resolve only from the stamp when a commercial profile is present.

---

## Remaining tracked work

| Area | Issue |
|---|---|
| Multi-component / named tax lines UI | #174 (separate from this tax-safety slice) |
| Labor policy tables for brand-new lines when labor profile lacks frozen rates (schema 1 legacy) | #178 follow-ups |
| Full parts matrix editor / calculator UI | #175 |
| Billing Classes UI merge into Labor | #180 |

---

## Provenance

`RepairOrderPricingContext::provenance()` exposes schema/version flags (including tax). Estimate snapshots include `commercial_pricing_profile`, `tax_profile`, and `pricing.provenance`, and freeze `customer.document_presentation_profile`.

---

## Rule precedence (current accepted order)

1. Line quantity × unit price (labor/parts already resolved)
2. Standing discount (commercial profile / customer class)
3. Shop fee (shop-fee profile + commercial class overrides)
4. Tax (tax profile via pricing context)
5. Totals; approved portions clamp invoice/authorization surfaces
