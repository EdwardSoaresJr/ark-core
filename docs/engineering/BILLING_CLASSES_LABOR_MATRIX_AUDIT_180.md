# Audit: Billing Classes vs Labor Rate Matrix (Core #180)

**Scope:** Investigation only. No UI rewrite, migrations, or deployment in this document.  
**Code tip audited:** Core `117605f` (`settings/labor-matrix-billing-minimums`).  
**Date:** 2026-11-10.

---

## Verdict (owner summary)

These are **related but not the same thing**. They must not be merged into one giant form.

| Concept | Storage | What it is |
|---|---|---|
| **Customer billing class** | `shop_settings.customer_types[]` + `customers.customer_type` (name string) | Who the customer is for fees, standing discounts, default parts matrix, document presentation, default concern billing |
| **Concern billing / labor column** | `repair_order_concerns.billing_posture` (`ConcernBillingPosture` or `custom_*`) | How this scope is priced for labor rates/minimums and which fee/parts posture applies |
| **Custom labor billing class** | `shop_settings.custom_labor_billing_classes[]` + `labor_policies.billing_posture` | Extra Labor Matrix columns with their own hourly rates/minimums |

**Recommendation:** Keep the approved vertical Labor Matrix as the rate/minimums editor. Keep a separate compact **Billing Classes** manager for customer-facing commercial rules. Share **identity/labels** where names align (Retail/Fleet/Warranty/…), but do not force Military-style customer classes into labor columns, and do not bury fee/discount/PDF settings inside each labor rate cell.

**Safety finding (confirmed):** Saving Billing Classes **does reprice living open ROs** and **rewrites open estimate document snapshots**. This contradicts the forward-only pricing rule used for Labor profiles (#178) and Shop Fees (#173). Treat as a **priority safety fix**, independent of UI unification.

---

## 1. Domain trace

```text
Customer.customer_type  ──name──►  shop_settings.customer_types[]
                                      │
                                      ├─ shop_fees_enabled / shop_fee_rate_override
                                      ├─ discount_type / discount_amount   (standing discount)
                                      ├─ default_parts_matrix_key
                                      ├─ document_presentation_profile
                                      ├─ pill_color
                                      └─ concern_billing_posture ──► default for NEW concerns
                                               │
                                               ▼
RepairOrderConcern.billing_posture  ◄── advisor selector (built-in + custom_*)
        │
        ├─ labor rates via LaborPolicy (operation_class × billing_posture)
        ├─ labor minimums via labor_billing_minimums[posture]
        ├─ fee eligibility via ConcernBillingPosture::shopFeePolicy()
        │     (named postures Fleet/Warranty/… look up customer_types by NAME)
        └─ parts matrix via ConcernBillingPosture::defaultPartsMatrix()

Labor Matrix columns = built-in postures
  (customer_pay→"Retail", warranty_other, fleet, repairpal, wholesale, internal, comeback)
  + custom_labor_billing_classes[]  (keys like custom_insurance)
```

### Key files

| Area | Path |
|---|---|
| Billing Classes UI | `resources/views/operations/settings/shop.blade.php` (`financial-panel-billing-classes`) |
| Labor Matrix UI | `resources/views/operations/settings/partials/labor-work-type-matrix.blade.php` |
| Save Billing Classes | `ShopFinancialSettingsController::updateCustomerTypes` |
| Labor columns | `LaborWorkTypeMatrix::matrixBillingColumns()` |
| Add labor class | `CreateLaborBillingClassAction` |
| Posture enum | `ConcernBillingPosture` |
| Totals | `EstimateTotalsCalculator::{recalculateRepairOrder,standingDiscountAllocations,shopFeeAllocations}` |
| Customer class change | `ApplyCustomerBillingClassChangeAction` |
| Open estimate refresh | `EstimateDocumentService::refreshOpenDocumentsForShopSettingsChange` |

### Built-in defaults (`ShopSettings::DEFAULT_CUSTOMER_TYPES`)

Retail, Fleet, Warranty, **RepairPal**, Internal, Wholesale, Comeback, Business, Military.

Special **name** matching (not stable IDs): Fleet / Warranty names feed fee and parts profiles for matching concern postures. Military ships with 10% labor standing discount.

### Custom labor classes vs customer types

`CreateLaborBillingClassAction` only writes `custom_labor_billing_classes` + `labor_policies` + minimums. It does **not** create a `customer_types` row.  
Billing Classes “Add” only appends `customer_types`. It does **not** create a Labor Matrix column or labor policies.

So today a shop can have:

- “Military” as a customer class with discount, **no** labor column.
- “Insurance” as a Labor column (`custom_insurance`), **no** customer class / fee / PDF profile.
- “RepairPal” as both a customer_types row **and** a labor column, while UI copy says RepairPal belongs under referral source.

---

## 2. Effect map (what each setting touches)

| Setting | Check-in / customer | New concern default | Labor rate / min | Parts matrix on new lines | Fees | Standing discount | PDF / presentation | Reports |
|---|---|---|---|---|---|---|---|---|
| `customer_types.name` + pill | Customer record label | — | — | — | — | — | Pill on RO | Filters by type |
| `concern_billing_posture` on customer type | — | Yes | Indirect (via posture) | Indirect | Indirect | — | — | — |
| Fee enable / % override | — | — | — | — | Live on recalc | — | Totals on docs | Fee totals |
| Standing discount | — | — | — | — | — | Live on recalc | Totals on docs | Discount totals |
| Default parts matrix | — | — | — | New part lines by posture/class | — | — | — | — |
| Document presentation | — | — | — | — | — | — | Customer PDF lines | — |
| Labor Matrix cell rate | — | — | New labor / posture change | — | — | — | — | Labor $ |
| Labor Rules (minimums) | — | — | Profile-stamped ROs | — | — | — | — | — |
| `custom_labor_billing_classes` | — | Advisor can select | Own policies | Uses `base_posture` for fee/parts | Via base | — | — | — |
| Referral source (RepairPal) | Customer field | Not billing class | — | — | — | — | — | Lead source |

**Customer changing class on the customer record** intentionally reprices open ROs (`ApplyCustomerBillingClassChangeAction`) and versions the estimate when totals change. That is a deliberate customer-record action, not a settings-editor side effect.

---

## 3. Duplicate / contradictory surfaces

1. **Two “Add Billing Class” paths** with different outcomes (Labor vs Billing Classes tab).
2. **Retail naming:** Labor column value `customer_pay`, label “Retail”; customer type name “Retail”; posture label “Customer pay”.
3. **RepairPal:** customer type + labor column + referral source warning = three stories.
4. **Military / Business:** customer commercial classes with no labor columns (correct product-wise; confusing if “unified” naively).
5. **Fee overrides live in customer_types**, but Labor Matrix also prices Fleet/Warranty/RepairPal labor independently via `labor_policies`.
6. **No shared stable ID** between customer type name and posture/custom key — rename breaks association silently (name string match).

---

## 4. Living-RO repricing — confirmed risk

### Code path

```php
// ShopFinancialSettingsController::updateCustomerTypes
$settings->update(['customer_types' => ...]);
$this->recalculateLivingRepairOrderTotals(); // all status != Closed
$this->syncOpenEstimateDocuments();          // rebuilds open estimate snapshots
```

`recalculateLivingRepairOrders()` loads every non-closed RO and rewrites each line’s:

- `standing_discount_cents` from **live** `customer_types` discount policy  
- `shop_fee_cents` from fee policy (shop-wide stamp/profile + **live** customer-type overrides for Default posture / named postures)  
- `tax_cents` / `total_cents`

It does **not** rewrite `unit_price_cents` (labor/part sell prices stay). So:

| Amount on existing open RO | Changed by Billing Classes save? |
|---|---|
| Labor / part unit prices | No (via this path) |
| Standing discounts | **Yes — live** |
| Shop fees (eligible lines) | **Yes — live overrides / named profiles** |
| Tax / line totals | **Yes, when fee/discount move** |
| Open estimate `snapshot_json` | **Yes — refreshed** |
| Closed ROs | Skipped by query |
| Issued invoice PDF snapshot | Refresh targets `document_type = estimate` only; issued invoices not rewritten by this helper |

### Test proof (already in repo)

`tests/Feature/Operations/BillingClassBlankDiscountTest.php`:

- Clearing Veteran discount via Billing Classes save moves an existing line from `$15` discount → `$0` and changes estimate total.
- Typing `12.5%` on Veteran immediately reprices the living RO line.

This is intentional in today’s tests, but **conflicts with the owner’s forward-only configuration rule** (Labor #178, Fees #173).

### Contrast with safer paths

| Settings save | Living RO recalc? | Notes |
|---|---|---|
| Labor Matrix / labor profile | No bulk living reprice of rates | New ROs stamp `labor_pricing_profile` |
| Shop Fees (#173) | **Stopped** after stamp of prior profile | Forward-only |
| Tax Rules | Still recalculates living | Parallel risk |
| Billing Classes | **Still recalculates living** | Confirmed |

### Recommended safety remediation (separate from UI)

1. Stop `recalculateLivingRepairOrderTotals()` on `updateCustomerTypes` (and decide on tax similarly).
2. Stamp commercial rules needed for fee/discount/presentation onto the RO (or keep deriving only for *new* lines / *new* ROs).
3. Keep `ApplyCustomerBillingClassChangeAction` when an advisor **explicitly** changes a customer’s class — that is a different intent.
4. Do not refresh already-sent customer documents without an explicit “revise estimate” flow (today any open estimate snapshot is rebuilt).

---

## 5. Recommended UX (preserve Labor Matrix)

**Do not** move the whole Billing Classes form into each Labor row.

### Labor tab (keep)

- Vertical work types (left)
- Inline rates per billing column (right)
- Rules pop-up (minimums)
- Add Work Type / Add Billing Class (labor column)
- Autosave rates/minimums
- Optional: “Class details…” link/icon on a column header → opens the shared class manager focused on that class

### Shared Billing Class manager (keep separate surface; compact)

Accessible from Financial → Billing Classes **and** optionally from Labor column headers.

Belongs here:

- Display name, pill color
- Default concern billing (for new scopes when this class is on the customer)
- Shop fee enable / override
- Standing discount
- Default parts matrix
- Document presentation profile
- Whether this class appears as a **customer** class, a **labor column**, or both (explicit flags — today implied)

Does **not** belong in Labor rate cells:

- Discounts, PDF presentation, customer pill, parts matrix default, fee %

### Suggested layout (no page-switch required)

```text
┌ Labor Matrix ─────────────────────────────────────────┐
│ Work types │ Billing Classes for Mechanical            │
│ Mechanical │ Retail  Warranty  Fleet  …  [+ Class]     │
│ Trouble…   │ $165    $…        $…     …  [Rules]       │
│            │        [⋯ details]                        │
└────────────────────────────────────────────────────────┘
         │
         ▼ compact modal (not a second page)
┌ Class: Fleet ──────────────────────────────────────────┐
│ Pill · Default concern billing · Fees · Discount       │
│ Parts matrix · Document presentation                   │
│ ☑ Show as customer class   ☑ Labor matrix column       │
│ Autosave / Saved                                        │
└────────────────────────────────────────────────────────┘
```

See companion mock-up: `review-artifacts/billing-classes-labor-matrix-audit-180/mockup.html`.

---

## 6. What stays where

| Capability | Labor Matrix | Billing Class manager | Elsewhere |
|---|---|---|---|
| Hourly rates / inherit / no charge | ✓ | | |
| Billing minimums (Rules) | ✓ | | |
| Add work type | ✓ | | |
| Add labor pricing column | ✓ (creates policies) | May also create customer class if flagged | |
| Customer list / check-in class | | ✓ name list | Customer record |
| Pill color | | ✓ | |
| Standing discount | | ✓ | |
| Fee override | | ✓ | Shop Fees tab = shop-wide default |
| Default parts matrix | | ✓ | Parts Matrix tab = matrices themselves |
| Document presentation | | ✓ | |
| Default concern billing | | ✓ | Concern Billing selector on RO |
| Referral / RepairPal lead source | | Do not treat as customer class | Customer referral field |
| Tax components | | | Tax tab (#174) |

---

## 7. Migration / versioning risks

- **No destructive rename** of `customer_types[].name` without a data migration: `customers.customer_type` stores the name.
- **Do not auto-delete** RepairPal customer type without owner decision; labor column and referral semantics differ.
- Unifying IDs should be **additive**: introduce stable `key` on customer types, keep `name` as label, backfill from slug(name), leave existing customer rows readable.
- Linking custom labor classes ↔ customer types should be optional (`customer_type_key` / `labor_column_key`), never forced 1:1.
- Commercial rule changes must become **forward-only** (stamp or “applies to new ROs only”) before any UX merge, or living totals will keep moving.
- Tenancy: all of the above is per-shop `shop_settings` JSON + tenant DB; no landlord move.

---

## 8. Recommended tests (when implementing)

1. Billing Classes save does **not** change discounts/fees/totals on pre-existing open ROs (new forward-only contract).
2. Explicit customer class change on customer record **does** update open ROs (preserve `ApplyCustomerBillingClassChangeAction`).
3. New RO after fee/discount settings change uses new rules; old RO adding a new labor line keeps old commercial stamp (define stamp shape).
4. Custom labor class appears in Labor Matrix + concern selector; without “customer class” flag does not appear in customer type dropdown.
5. Military-style customer class with discount does not require a labor column.
6. RepairPal referral source ≠ customer billing class; labor RepairPal column remains selectable on concerns.
7. Mixed RO: Retail concern + Warranty concern; class fee/discount only hits eligible scopes.
8. Open estimate snapshot not silently refreshed on settings save once safety fix lands; closed/issued documents untouched.
9. Rename/label change does not break customers when stable keys exist.
10. Regression: `ShopFeeFlatDollarTest` + labor profile stamp tests still pass.

---

## 9. Implementation phases (owner-gated)

| Phase | Work | Deploy? |
|---|---|---|
| **A — Safety** | Stop living recalc + document sync on Billing Classes save; document remaining live-read paths; add forward-only tests | Staging only after review |
| **B — Identity** | Additive stable keys; optional link labor column ↔ customer class; clarify RepairPal | Staging |
| **C — UX** | Compact class details modal from Labor column + slim Billing Classes tab; no rate-row cram | Staging |
| **D — Parts/Tax coordination** | Align with #175 / #174 managers the same way (matrix editor ≠ commercial class editor) | Later |

**Do not** remove the Billing Classes tab or force unification until Phases A–B are accepted.

---

## Related issues

- #178 Labor Matrix polish (keep layout)
- #173 Shop Fees forward-only profiles (precedent)
- #175 Parts Matrix editor
- #174 Multi-component tax
- #179 Settings nav weight (done)

---

## Owner decisions needed

1. Confirm Safety Phase A (stop living reprice on Billing Classes save) as a standalone fix.
2. Confirm Military/Business-style classes remain **customer-only** (no forced labor column).
3. Confirm RepairPal: keep labor column; demote/remove as default **customer** class in favor of referral source (or keep for legacy shops).
4. Approve “shared identity, two editors” vs any desire for a single combined page (not recommended).
