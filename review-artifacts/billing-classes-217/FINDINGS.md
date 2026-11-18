# Billing Classes Investigation — Issue #217

**Date:** 2026-11-18  
**Scope:** Investigation and UI demonstration only. No product redesign implemented.  
**Live local demo:** `https://ark-core.test` (investigation fixtures; not production).

Screenshots: `review-artifacts/billing-classes-investigation-217/screenshots/`

---

## Verdict

ARK does **not** have one billing-class system today. It has **two registries** that share some names and an optional link map:

| Registry | Storage | What advisors assign |
|---|---|---|
| **Customer Billing Classes** | `shop_settings.customer_types[]` + `customers.customer_type` (display **name**) | Who the customer is commercially |
| **Labor Billing Classes / columns** | Built-in `ConcernBillingPosture` enum + `shop_settings.custom_labor_billing_classes[]` + `labor_policies.billing_posture` | How a concern / labor line is priced |

The RO Builder concern picker reads **labor columns** (enum + custom labor), **not** the customer-types catalog. That is the core of #217.

**Dollar impact:** Label mismatch (`Retail` vs `Customer pay`) is display-only for the same key `customer_pay`. Selecting a different labor column **does** change rates/fees/parts when those columns differ (e.g. RepairPal `$150` vs Retail `$165`). Choosing a customer-only class like Military/Business never appears as a concern billing option; its commercial effects apply via customer assignment + standing discount / fee / parts rules, not as a labor column.

---

## 1. Customer Billing Classes — where they live

### Routes

| Action | Route | Name |
|---|---|---|
| Shop settings page (host UI) | `GET /app/settings/shop?section=financial&financial-tab=labor` | `operations.settings.shop.edit` |
| Catalog / list | `GET /app/settings/shop/customer-types` | `operations.settings.shop.customer-types.show` |
| Replace-all | `PATCH /app/settings/shop/customer-types` | `operations.settings.shop.customer-types.update` |
| Create | `POST /app/settings/shop/customer-types` | `operations.settings.shop.customer-types.store` |
| Update one | `PATCH /app/settings/shop/customer-types/{classKey}` | `operations.settings.shop.customer-types.item.update` |
| Archive / restore / delete | `POST …/archive`, `POST …/restore`, `DELETE …/{classKey}` | item.* |

### User-facing surfaces

1. **Shop Settings → Financial Rules → Labor → Class Settings gear** (labor-linked classes) and **Customer-only & archived** (Manage Billing Classes modal).  
   - Views: `resources/views/operations/settings/partials/billing-classes-shared.blade.php`, included from `shop.blade.php`  
   - Screenshot: `02-fleet-class-settings.png`, `05-manage-with-insurance-customer.png`
2. **Customer record / Edit Customer → Billing class**  
   - `resources/views/operations/customers/partials/hub-workspace-modal.blade.php`  
   - Screenshot: `08-customer-edit-billing-class.png`  
   - Persisted as `customers.customer_type` **name string**
3. **RO Builder customer identity pill** (displays customer type; not the concern labor selector)  
   - Screenshot: `06-ro-builder-overview.png` (“Retail” pill)
4. **Intake / leads / customer search** filters and create forms use `customerTypes` from shop settings.

### Pricing / commercial effects (customer class)

Driven from `customer_types[]` (and stamped `commercial_pricing_profile` on ROs):

- Standing discounts (`discount_type` / `discount_amount`)
- Shop fee enable / override
- Default parts matrix key
- Document presentation (part # / brand / vendor)
- Pill color
- Optional `concern_billing_posture` default for **new** concerns
- Explicit customer class change: `ApplyCustomerBillingClassChangeAction` restamps commercial profile and recalculates open ROs

Authority: `PersistCustomerBillingClasses`, `CustomerBillingClassLifecycle`, `CustomerBillingClassIdentity`.

Defaults: `ShopSettings::DEFAULT_CUSTOMER_TYPES` — Retail, Fleet, Warranty, RepairPal, Internal, Wholesale, Comeback, Business, Military.

---

## 2. Labor Billing Classes — where they live

### Routes

| Action | Route | Name |
|---|---|---|
| Labor matrix JSON | `GET /app/settings/shop/labor-matrix` | `operations.settings.shop.labor-matrix.show` |
| Rate / min / rounding | `PATCH …/labor-matrix/rate|minimum|rounding|work-type-rounding` | … |
| Add labor column | `POST …/labor-matrix/billing-classes` | `operations.settings.shop.labor-matrix.billing-classes.store` |
| Archive / restore / destroy custom column | `POST …/archive|restore|destroy` | … |

### User-facing surfaces

1. **Shop Settings → Financial Rules → Labor → Labor rate matrix**  
   - Screenshot: `01-labor-matrix.png`  
   - Columns = built-ins labeled Retail / Warranty / Fleet / … + custom columns  
   - `LaborWorkTypeMatrix::matrixBillingColumns()`  
   - View: `resources/views/operations/settings/partials/labor-work-type-matrix.blade.php`
2. **+ Add Labor Column** → `CreateLaborBillingClassAction` (writes `custom_labor_billing_classes` + `labor_policies` + minimums). Explicitly does **not** create a customer class.
3. **Class Settings** gear → opens shared commercial editor for the **linked** customer class (`customer_class_key`), not the labor rate itself.
4. **RO Builder concern Billing dropdown**  
   - Screenshot: `07-ro-concern-billing-dropdown.png`  
   - View: `resources/views/operations/repair-orders/partials/repair-order-concern-billing-control.blade.php`  
   - Options: `ConcernBillingPosture::advisorSelectableCases()` **plus** `customLaborBillingClasses()`  
   - Persist: `PATCH …/concerns/{concern}/billing-posture` (`RepairOrderConcernBillingPostureController`)
5. **Visit-level Billing** on the RO identity panel  
   - Enum postures **only** — custom labor columns are **not** accepted (`RepairOrderBillingPostureController::validatedPosture`)

### Interactions

- **Labor rates:** `labor_policies` keyed by `billing_posture` (enum value or `custom_*`); matrix UI stamps `labor_pricing_profile` on new ROs (forward-only rates).
- **Labor rules / minimums:** `labor_billing_minimums[posture]` via matrix Min Hrs / Rules.
- **Class Settings:** fees, parts matrix, document flags on the linked `customer_types` row.
- **Concerns:** `repair_order_concerns.billing_posture` stores raw key; cast maps custom_* → `base_posture` for fee/parts enum behavior (`ConcernBillingPostureCast`).
- **Customers:** labor columns do not appear in the customer Billing class dropdown unless a customer_types row exists.
- **Pricing snapshots:** `labor_pricing_profile`, `commercial_pricing_profile`, `shop_fee_profile`, `tax_profile` via `RepairOrderPricingContext`.

---

## 3. Side-by-side comparison

| Dimension | Customer Billing Classes | Labor Billing Classes |
|---|---|---|
| Storage | `shop_settings.customer_types` JSON | Enum + `custom_labor_billing_classes` + `labor_policies` |
| Canonical id | `key` (e.g. `retail`, `insurance`) | Posture value / `custom_insurance` |
| Assignment value on records | `customers.customer_type` = **name** | `billing_posture` = **key** |
| UI create | Manage → + Add Billing Class | Labor matrix → + Add Labor Column |
| Labels | Retail, Fleet, … | Matrix: Retail; RO Builder: **Customer pay** for same `customer_pay` |
| Pricing | Discounts, fees, parts default, PDF | Hourly rate, rounding, minimums |
| Defaults / inheritance | New concern posture from customer type; standing discount on eligible scopes | New RO stamps labor profile; concern defaults from customer / visit |
| Historical | Commercial stamp on RO; name-based customer rows | Frozen labor profile rates; raw posture keys on concerns |

### Creating **Fleet** or **Insurance** (live demonstration)

**Fleet (built-in, already linked):**

- Customer types: key `fleet`, `labor_column_key=fleet`
- Labor matrix: column `fleet` labeled Fleet
- RO Builder: option `fleet` labeled Fleet  
→ Appears in both places as one linked pair.

**Insurance (created both ways in local demo):**

| Create path | Result | Appears in customer dropdown? | Appears in Labor matrix? | Appears in RO concern Billing? |
|---|---|---|---|---|
| Manage → Add Billing Class “Insurance” | `customer_types` key `insurance`, **no** labor column | Yes | No | **No** |
| Labor → Add Labor Column “Insurance” | `custom_insurance` + labor policies | No (unless separate customer row) | Yes | **Yes** |

Screenshot proof: `05-manage-with-insurance-customer.png` shows **both** “Insurance — Customer only” and “Insurance — Labor rates only” as separate rows with the same display name.

---

## 4. Issue #217 — RO Builder path

### What the RO Builder reads

Concern control (`repair-order-concern-billing-control.blade.php`):

1. Loop `ConcernBillingPosture::advisorSelectableCases()` — includes `default`, `customer_pay`, warranty/fleet/… (**excludes** deprecated `warranty` / `warranty_repairpal`).
2. Loop `ShopSettings::customLaborBillingClasses()` — active custom labor columns only.
3. Labels from `$option->label()` → **“Customer pay”**, not Labor matrix’s **“Retail”**.
4. On change → `ChangeConcernBillingPostureAction` → `LaborAuthority` resolves rate from RO `labor_pricing_profile` if frozen, else live `labor_policies`.

### Legacy still active

- Enum `ConcernBillingPosture` remains the built-in labor column registry (not shop JSON).
- Deprecated posture values still migrate via `fromStored()`.
- Fee/parts for named postures still resolve customer_types **by display name** (`billingClassName()` → `shopFeePolicyForCustomerType('Fleet')`).
- Visit-level billing UI/controller still enum-only (cannot select `custom_*`).
- Customer assignment still stores **names**, not keys.

### Display vs dollars

| Symptom | Affects dollars? |
|---|---|
| “Retail” (settings) vs “Customer pay” (RO) | No — same `customer_pay` key/rate |
| Custom labor “Insurance” selectable on concern | Yes — uses `custom_insurance` labor policies / profile cell |
| Customer-only “Insurance” / Military / Business missing from concern picker | By design today; dollars for those classes come from customer commercial rules when assigned to the customer, not from picking them as a labor column |
| Selecting RepairPal vs Retail on a concern | Yes — different hourly rates (demo: `$150` vs `$165`) |

---

## 5. Unified system evaluation

### Target model (product owner)

One Billing Class registry, managed in one place, available everywhere billing classes are used — with **attributes** (labor rates, parts, discounts, fees) on each class rather than separate identities.

### What must stay distinct *as attributes*, not as second registries

- Labor hourly rate / rounding / minimums (work type × class)
- Standing discount
- Shop fee enable / override
- Default parts matrix
- Document presentation
- Whether the class is assignable to customers
- Whether the class is selectable as concern labor billing
- Base commercial posture for fee/parts edge cases (today `base_posture` on custom labor)

### Feasibility on current architecture

**Yes, with a single canonical registry** and flags/attributes:

1. One `billing_classes[]` (or promote `customer_types` + fold `custom_labor_billing_classes` into it).
2. Stable `key` everywhere; stop assigning customers by display name.
3. Explicit links already sketched in `CustomerBillingClassIdentity` (`labor_column_key`, built-in map) — extend so custom classes can opt into both roles under one key.
4. RO Builder options = classes with `selectable_on_concern=true`, labeled from the registry (one label).
5. Labor matrix columns = classes with `has_labor_column=true`.
6. Keep RO stamps (`labor_pricing_profile`, `commercial_pricing_profile`) forward-only; never rewrite frozen approved amounts from settings edits.

### Migration / protection risks

- Dual “Insurance” rows (customer `insurance` vs labor `custom_insurance`) must be merged or explicitly linked before collapsing UI.
- `customers.customer_type` name → key migration.
- Existing concerns storing `custom_*` / enum values must keep resolving after renames.
- Visit billing must accept the same set as concern billing if product intends one selector model.
- Name-based fee/parts lookups must move to keys.
- Do not reprice approved/frozen ROs on class registry edits; keep explicit customer-class-change and concern-billing-change as intentional actions.

### Recommendation

Adopt **one canonical Billing Class registry** with attribute panes (Labor / Commercial / Documents), not two independent “Add Billing Class” paths. Do **not** keep two registries merely because that is the current shape. Phase: (1) stop dual-create and align RO labels/options to registry, (2) migrate storage/keys, (3) protect stamps and approved dollars with tests.

---

## 6. Decisions requiring product-owner approval

1. Confirm **single registry** as the product target (vs shared labels only).
2. Confirm customer-only classes (Military, Business, discounts-without-rate-column) remain valid **attributes** (`has_labor_column=false`).
3. Confirm labor-only columns (Insurance rates without customer assignment) remain valid (`assignable_to_customer=false`) or must always create both.
4. Resolve **Retail vs Customer pay** → one user-facing label.
5. Resolve **RepairPal** as referral source vs billing class vs labor column.
6. Confirm visit-level billing must include custom classes (today it does not).
7. Confirm merge strategy for duplicate display names already created in shops.
8. Confirm forward-only rules for commercial stamps vs intentional customer-class change repricing.

---

## Source file index (current code)

- `app/Ark/Operations/EstimatePricing/CustomerBillingClassIdentity.php`
- `app/Ark/Operations/EstimatePricing/CreateLaborBillingClassAction.php`
- `app/Ark/Operations/EstimatePricing/LaborWorkTypeMatrix.php`
- `app/Ark/Operations/EstimatePricing/RepairOrderPricingContext.php`
- `app/Ark/Operations/Settings/PersistCustomerBillingClasses.php`
- `app/Ark/Operations/Settings/CustomerBillingClassLifecycle.php`
- `app/Ark/Operations/Settings/CustomLaborBillingClassLifecycle.php`
- `app/Ark/Operations/Settings/LaborMatrixSettingsController.php`
- `app/Ark/Operations/Settings/ShopFinancialSettingsController.php` (customer-types endpoints)
- `app/Ark/Operations/RepairOrders/ConcernBillingPosture.php`
- `app/Ark/Operations/RepairOrders/ConcernBillingPostureCast.php`
- `app/Ark/Operations/RepairOrders/ChangeConcernBillingPostureAction.php`
- `app/Ark/Operations/RepairOrders/RepairOrderConcernBillingPostureController.php`
- `app/Ark/Operations/RepairOrders/RepairOrderBillingPostureController.php`
- `app/Ark/Operations/Customers/ApplyCustomerBillingClassChangeAction.php`
- `resources/views/operations/settings/partials/billing-classes-shared.blade.php`
- `resources/views/operations/settings/partials/labor-work-type-matrix.blade.php`
- `resources/views/operations/repair-orders/partials/repair-order-concern-billing-control.blade.php`
- `routes/operations/settings.php`
