# #180 Labor × Billing Classes — Implementation Readiness

**Scope:** Audit + implementation plan only. No UI rewrite, migration, commit, or deploy in this document.  
**Audited tip:** Core `3cd75c3ee2f6847045c15a3aa24e1f32cd324a33` (`settings/labor-matrix-billing-minimums`).  
**Staging verified:** Core `3cd75c3…` / Stancl `f38531f40f159e8cf6984b7d310a7a7da6f52c22` on `157.230.172.143`.  
**Date:** 2026-11-10.

---

## A. Executive verdict

**YES WITH SPECIFIC BLOCKERS**

The pricing-engine foundation (#181–#183 + tax-safety #174) is sufficient to build the Labor-hosted Class Settings experience without another commercial calculator. The Labor Matrix UI itself is present and usable on staging. What is missing is the **in-Labor commercial editor + Manage Billing Classes**, plus a deliberate mapping layer between labor columns and `customer_types` that does not invent a second pricing authority.

Hard safety blockers from the original #180 audit (living RO reprice / estimate rewrite on Billing Classes save) are **resolved**. Remaining blockers are product/UX sequencing choices (identity keys timing; keep Rules separate from Class Settings; do not retire the standalone tab until parity), not foundation gaps.

---

## B. Decision ledger

| # | Decision | Source | Status | Code today | Conflict? |
|---|---|---|---|---|---|
| 1 | Labor Matrix and Billing Classes are related, not identical | Audit `173c934`; owner #180 2026-11-10 15:26 | **Final** | Separate stores + editors | No |
| 2 | Do not cram commercial fields into rate cells | Owner #180 15:31 | **Final** | Rate cells = mode/rate only; Rules = minimums | No |
| 3 | Class Settings inside Labor (modal/side sheet, no page switch) | Owner #180 15:31 **supersedes** audit “keep standalone as primary” | **Final** | **Missing** | Docs audit §5 still recommends standalone-as-primary — **superseded** |
| 4 | Manage Billing Classes from Labor (incl. Military/Business) | Owner #180 15:31 | **Final** | **Missing** | No |
| 5 | Reuse one commercial editor/authority | Owner #180 15:31 | **Final** | Only Financial → Billing Classes form exists | No |
| 6 | Keep standalone Billing Classes tab until parity + owner visual OK | Owner #180 15:31 | **Final** | Tab still present | No |
| 7 | Military/Business remain customer-only (no forced labor columns) | Owner #180 15:26 / 15:31 | **Final** | No labor columns for them | No |
| 8 | Do not demote/remove RepairPal customer data without approval | Owner #180 15:26 / 15:31 | **Final** | RepairPal remains customer type + labor column + warning | No |
| 9 | No automatic identity merge by display name alone | Owner #180 15:31 | **Final** | Links still use name/posture conventions | Soft risk until explicit map |
| 10 | Safety first: stop living reprice on Billing Classes save (#181) | Owner #180 15:26 → #181 `c21e895` | **Final / done** | Stamp + no living recalc/sync | Audit §4 text is **stale** (still describes old reprice) |
| 11 | Preserve approved vertical Labor Matrix | #178 / #180 | **Final** | Present on staging | No |
| 12 | Additive stable `customer_types` keys | Audit Phase B; owner “identity mapping” after #181 | **Conditional / unresolved timing** | Names only | Not implemented |
| 13 | Combine Rules + Class Settings into one modal? | Owner “consider if intuitive” | **Unresolved preference** | Separate Rules modal only | N/A |
| 14 | Rename Labor “+ Add Billing Class” vs customer Add | Implied by dual create paths | **Unresolved UX copy** | Two different creates, same label | Naming conflict |

---

## C. Current-state inventory

### Labor Matrix (preserve)

| Behavior | Classification | Evidence |
|---|---|---|
| Vertical Work Type nav | Implemented and verified (staging) | `labor-work-type-matrix.blade.php`; demo Labor tab screenshot |
| Selected Work Type editing | Implemented and verified | Troubleshooting heading updates in DOM |
| Inline rate editing + autosave | Implemented and verified (tests + UI Ready state) | `LaborMatrixSettingsController::updateRate`; `LaborWorkTypeMatrixSettingsTest` |
| Default Labor Rate | Implemented and verified | Staging $165.00; inherit count shown |
| Use Default / Custom / No Charge | Implemented and verified | Retail / Warranty / Internal rows |
| Built-in billing columns | Implemented and verified | Retail…Comeback |
| Custom labor columns | Implemented and verified (tests) | `CreateLaborBillingClassAction`; matrix tests |
| Rules (minimums) modal | Implemented and verified (code + UI button) | Separate from commercial settings |
| Autosave status Ready/Saving/Saved | Implemented and verified | Staging “Ready” |
| Selected Work Type persistence across reload | Partially implemented | Alpine in-memory only; resets to first work type |
| Compact Tabler styling / responsive | Implemented (staging desktop verified) | Mobile width not re-measured this pass |
| Class Settings on row | **Missing** | Only Rules |
| Manage Billing Classes in Labor | **Missing** | Must leave Labor → Billing Classes tab |
| “+ Add Billing Class” creates labor column only | Implemented but label-confusing | Does not create `customer_types` |

### Billing Classes tab (preserve fields)

| Field | Classification | Persistence |
|---|---|---|
| Name | Implemented | `customer_types[].name` → customers by name |
| Pill color + preview | Implemented | `pill_color` |
| Document presentation | Implemented | `document_presentation_profile` |
| Shop fees enabled | Implemented | `shop_fees_enabled` |
| Fee rate override | Implemented (conditional) | `shop_fee_rate_override` |
| Default parts matrix | Implemented | `default_parts_matrix_key` |
| Standing discount type/amount | Implemented | `discount_type` / `discount_amount` |
| New concerns bill as | Implemented | `concern_billing_posture` |
| Built-ins + Military/Business | Implemented | `DEFAULT_CUSTOMER_TYPES` |
| Special Fleet/Warranty name profiles | Implemented | Name-matched fee/parts helpers |
| RepairPal warning vs referral | Implemented (copy) | Warning on Billing Classes panel |
| Forward-only save | Implemented and verified (tests) | `updateCustomerTypes` stamps; no living recalc/sync |

---

## D. Source-of-truth map

```text
Customer billing class
  shop_settings.customer_types[]  (name string identity today)
  customers.customer_type
  stamped → repair_orders.commercial_pricing_profile
  resolved via RepairOrderPricingContext

Concern billing posture
  repair_order_concerns.billing_posture
  ConcernBillingPosture enum | custom_*
  labor rates/minimums; fee/parts via posture→class name for named postures

Labor Matrix column
  built-ins from ConcernBillingPosture (Retail label = customer_pay)
  + shop_settings.custom_labor_billing_classes[]
  labor_policies / labor_billing_minimums
  stamped → repair_orders.labor_pricing_profile

Shop fee mode
  shop_settings + ShopFeeProfile stamp

Tax
  shop_settings + TaxProfile stamp

Authority for totals
  EstimateTotalsCalculator + RepairOrderPricingContext
  (do not add a parallel commercial resolver in the UI)
```

**Mapping today (conventional, not a stable ID table):**

| Labor column value | Label | Linked customer type (by name) |
|---|---|---|
| `customer_pay` | Retail | `Retail` (convention; `billingClassName()` returns null — fees use shop default) |
| `warranty_other` | Warranty | `Warranty` |
| `fleet` | Fleet | `Fleet` |
| `repairpal` | RepairPal | `RepairPal` |
| `wholesale` | Wholesale | `Wholesale` |
| `internal` | Internal | `Internal` |
| `comeback` | Comeback | `Comeback` |
| `custom_*` | custom label | **None** unless separately created |
| — | — | Military, Business (customer-only) |

---

## E. UI gap analysis

**Keep unchanged:** vertical work types, rate modes, Rules minimums, Default Labor Rate strip, autosave, Financial → Billing Classes tab (during transition).

**Add to Labor:**

1. Per applicable row: **Class Settings** → shared commercial editor (same fields as Billing Classes card) in modal/side sheet; copy that rate is work-type-specific, class settings are shared.
2. Toolbar: **Manage Billing Classes** → full `customer_types` list including Military/Business.
3. Clarify **+ Add labor billing column** vs add customer class (copy and/or dual actions).
4. Optional: persist selected work type (query/local) — nice-to-have, not required for Class Settings.

**Do not:** embed discount/fee/parts/color into rate cells; auto-create customer classes from custom labor columns; remove Billing Classes tab in the first ship.

---

## F. Safety regression analysis

| Scenario | Status | Evidence |
|---|---|---|
| Billing Class edit keeps open RO commercial rules | Verified (tests) | `CommercialPricingProfileForwardOnlyTest`, `BillingClassBlankDiscountTest` |
| New line on old RO uses frozen commercial rules | Verified (tests) | same |
| Parts matrix shop edit does not silently reprice stamped RO | Verified (tests) | `PricingEngineSafetyTest`, `PartsMatrixAndTaxForwardOnlyTest` |
| Tax settings forward-only | Verified (tests + staging migration) | `TaxProfile`; tax update stamps; no living recalc |
| Labor rate edit does not reprice stamped RO rates | Verified (tests) | `LaborWorkTypeMatrixSettingsTest` existing-RO case |
| Explicit concern/customer billing change still recalculates | Verified (code + tests) | `ApplyCustomerBillingClassChangeAction`; commercial forward-only explicit-change test |
| Closed/archived amounts retained | Verified (tests) | commercial + pricing engine safety |
| Mixed-billing concerns | Verified (tests) | mixed Retail/Warranty commercial test |
| Estimate snapshot not rewritten on Billing Classes save | Verified (tests) | `billing classes settings save does not refresh open estimate snapshots` |

**Residual (out of #180 UI scope, note only):** `updateFees` still calls `syncOpenEstimateDocuments()` after stamping fee profiles (line ~352). Living fee *amounts* are not recalculated, but open estimate snapshots may still refresh on Shop Fees save.

Original audit §4 still describing living reprice on Billing Classes save is **stale** relative to `c21e895` / tip `3cd75c3`.

---

## G. Implementation sequence

### Phase 1 — Mapping helper (no schema required for first ship)

**Scope:** Explicit read-only map labor column → optional `customer_types` row; list customer-only classes; do not merge by fuzzy name beyond built-in table.  
**Files:** new small helper under `EstimatePricing/` or `Settings/`; consume from Labor blade/controller.  
**Migrations:** none required for v1. Additive `customer_types[].key` optional follow-up.  
**Tests:** mapping unit/feature for built-ins, custom labor-only, Military/Business.  
**Risk:** Low. **Independent:** Yes.

### Phase 2 — Class Settings in Labor

**Scope:** Row action opens shared commercial editor (extract from Billing Classes card markup into a Blade/Alpine component). PATCH through existing `customer-types.update` (or narrow single-class endpoint reusing `normalizeCustomerTypes`). Preserve work type selection. Keep Rules separate.  
**Files:** `labor-work-type-matrix.blade.php`, `shop.blade.php` (extract), possibly `ShopFinancialSettingsController`, routes.  
**Migrations:** none.  
**Tests:** open/save Class Settings from Labor; settings appear on Billing Classes tab; forward-only still holds.  
**Risk:** Medium (parity of all fields). **Depends on:** Phase 1.

### Phase 3 — Manage Billing Classes

**Scope:** Labor toolbar opens full class list (customer-only included); same component; create customer class without forcing labor column.  
**Files:** Labor partial + shared component; create path for `customer_types` only.  
**Tests:** Military editable from Labor; custom labor column create unchanged.  
**Risk:** Medium. **Depends on:** Phase 2.

### Phase 4 — Validation / visual acceptance

**Scope:** Built-in + custom; autosave rates still work beside Class Settings; tenancy; historical stamps; screenshots.  
**Risk:** Low–medium. **Independent review:** Yes as QA gate.

### Phase 5 — Retire standalone tab (owner-gated)

**Scope:** Remove/redirect Financial → Billing Classes after parity + deep links + owner OK.  
**Risk:** Medium (navigation/deep links). **Blocked until:** Phase 4 owner approval.

---

## H. Owner decisions still needed

1. **Stable keys now or later?** Ship Class Settings on today’s name/posture map (Phase 1 helper), or require additive `customer_types[].key` before UI work?
2. **Keep Rules and Class Settings as two actions?** (Recommended: yes — different scope.)
3. **Copy for Labor “+ Add Billing Class”** — rename to “+ Add labor column” / split actions?

Settled (do not re-ask): Military/Business customer-only; keep RepairPal customer rows; no rate-cell cram; Class Settings + Manage from Labor; don’t retire tab until parity; reuse pricing context.

---

## I. Evidence

| Item | Ref |
|---|---|
| Core tip | `3cd75c3ee2f6847045c15a3aa24e1f32cd324a33` |
| Staging stancl | `f38531f40f159e8cf6984b7d310a7a7da6f52c22` |
| Prior audit | `docs/engineering/BILLING_CLASSES_LABOR_MATRIX_AUDIT_180.md` (`173c934`) — §5 primary-standalone **superseded** |
| Mock-up | `review-artifacts/billing-classes-labor-matrix-audit-180/mockup.html` |
| Staging shots | `…/01-staging-labor-mechanical.png`, `…/02-staging-labor-troubleshooting.png` |
| Issue | https://github.com/EdwardSoaresJr/ark-core/issues/180 |
| Safety tests | `CommercialPricingProfileForwardOnlyTest`, `BillingClassBlankDiscountTest`, `PricingEngineSafetyTest`, `PartsMatrixAndTaxForwardOnlyTest`, `LaborWorkTypeMatrixSettingsTest` |

**Not claimed:** owner visual acceptance of a Class Settings modal (does not exist yet); mobile re-measure this pass; production behavior.
