# #217 billing-class consolidation — implementation

Branch `fix/billing-classes-217-canonical`. Not merged, not deployed.

## Model

- **One registry**: `shop_settings.customer_types[]` (stable `key`, `labor_column_key`). Manage Billing Classes and the
  labor matrix "+ Add Billing Class" both call `CreateCanonicalBillingClassAction` (via
  `PersistCustomerBillingClasses::create`): one customer row + a linked labor column (built-in link, or new `custom_*`
  column with labor policies and minimums). Names never merge; a duplicate name is a validation error.
- **Customer assigns class**: `customers.billing_class_key` is identity; `customer_type` stays as the synced display name for
  legacy readers (`Customer::saving` keeps them consistent). Helpers: `billingClassKey()`, `billingClassName()`,
  `billingClassLaborColumnKey()`.
- **Concern inherits**: `repair_order_concerns.billing_inherits_customer`. `inheritsCustomerBilling()` = flag true, or
  (flag null and raw posture `default`). `effectiveBillingClassKey()` = customer's labor column (retail → `customer_pay`)
  or the stored override. Writing `billing_posture` without the flag is an explicit choice (`default` → inherit,
  anything else → override). The `billing_posture` cast resolves an inheriting concern to the customer's fee/parts posture
  (Retail, custom and unlinked classes stay on `Default`, so the class row supplies fees/parts) and never caches it.
- **Override / clear**: concern billing route accepts catalog labor keys or `inherit` (`ChangeConcernBillingPostureAction::inherit`).
  Override sets flag=false. Neither touches the customer or sibling concerns.
- **No RO billing**: visit-billing panel and identity Billing row removed; `billing-posture.preview|update` routes return 410 /
  redirect with a message; `ChangeRepairOrderBillingPostureAction` deleted; `repair_orders.default_billing_posture` kept,
  never written, never read for new concerns. All concern-creating paths use `RepairOrderConcern::inheritedBillingAttributes()`.
- **Customer class change**: `ApplyCustomerBillingClassChangeAction` reprices policy-priced labor on inheriting concerns to
  the new customer labor column (`repriceInheritedForCustomerChange`), then restamps commercial rules and recalculates as before.
  Override concerns are untouched. Approved, manual-rate and special-priced lines keep their rate; the existing
  `requires_additional_authorization` flag still fires when the total rises over approved work.
- **Labels**: `customer_pay` is "Retail" everywhere; concern labels come from the catalog (`labelForLaborColumn`).

## Migration

Adds both nullable columns. Backfill: `billing_inherits_customer = true` where posture is `default`, NULL otherwise (NULL +
non-default = override, so ambiguous history is not rewritten); `billing_class_key` from case-insensitive name match.
Customer-only classes get a linked custom labor column; collisions are logged and listed in `MIGRATION_COLLISIONS.md`.

## Files

Core: `CreateCanonicalBillingClassAction`, `CanonicalBillingClassCatalog`, `PersistCustomerBillingClasses`,
`CustomerBillingClassIdentity`, `LaborMatrixSettingsController`, `Customer`, `CustomerUpdateController`, `RepairOrderConcern`,
`ConcernBillingPostureCast`, `ConcernBillingPosture`, `ChangeConcernBillingPostureAction`, `RepairOrderConcernBillingPostureController`,
`RepairOrderBillingPostureController`, `ApplyCustomerBillingClassChangeAction`, `LaborAuthority`, `ApplyBillingClassMinimumsAction`,
`ShopSettings` (memoized `customerTypeRows`), concern creators (mobile, intake, templates, oil service, testing package, recommendations, deferred, visit reason).
Views: concern billing control, identity panels, identity panel, hub customer modal, labor matrix, billing-classes-shared. JS: `ark-workspace-modal.js`.
Tests: `CanonicalBillingClassWorkflowTest` (new, includes closed-RO refusal); updated `RepairOrderBillingPostureTest`,
`ConcernBillingControlTest`, `BillingClassSettingsConsistencyTest`, `CustomerBillingClassJsonApiTest`,
`LaborWorkTypeMatrixSettingsTest`.

## Decisions and limits

- A legacy class with **no labor link** that sets "New concerns bill as" keeps using that posture as its labor column for
  inheriting concerns. Once linked (all migrated classes), the link wins and that field is ignored. The field is still in
  Class Settings; removing it is a follow-up.
- The class change's "prior total" is computed after the customer is saved, so fee differences that depend only on posture are
  not in the prior figure (same skew existed for default-posture concerns).
- `CommercialPricingProfile` stamps are unchanged. Class settings edits stay forward-only for stamped ROs.
