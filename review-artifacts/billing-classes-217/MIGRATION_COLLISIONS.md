# Billing class migration collisions (#217)

Migration: `database/migrations/2026_11_15_100000_add_billing_inheritance_and_customer_class_key.php`
Linker: `CreateCanonicalBillingClassAction::linkLegacyCustomerOnlyClasses()`

## Rule

A legacy customer-only class (no `labor_column_key`, e.g. Business, Military) gets its own linked
`custom_*` labor column at default rates. If a labor-only column with the **same label
(case-insensitive)** already exists, nothing is merged and nothing is renamed:

- the customer class stays unlinked (customer assignment, commercial rules, retail labor rates);
- the existing labor-only column stays a selectable concern override and a labor matrix column;
- a `billing_class_collision` warning is logged with `class_key`, `name`, `existing_labor_column_key`.

Unlinked classes price labor at Retail (`customer_pay`) until an owner links or retires one side.
Customers matched to an ambiguous name resolve to the first catalog row of that name.

## Known collisions in this repo

None in code, defaults or fixtures. `ShopSettings::DEFAULT_CUSTOMER_TYPES` has no `custom_*` labor-column
name overlap (Business, Military link cleanly to `custom_business`, `custom_military`).

## Known collision in the local investigation fixture (#217 FINDINGS.md)

| Customer class key | Name | Existing labor-only column | Result |
|---|---|---|---|
| `insurance` | Insurance | `custom_insurance` | Both preserved, not merged. Customer class stays unlinked. |

## Production

The migration cannot know production data. After it runs, read the log for `billing_class_collision` and
`billing_class_legacy_links`, and append real collisions here before closing the issue. Covered by
`CanonicalBillingClassWorkflowTest` ("a name collision with a labor-only column is reported and both are preserved").
