# Adjust Labor audit (#194)

**Decision recorded:** retire the generic Adjust Labor / Allow Modifiers **control** from the Labor Matrix UI. Do **not** delete enforcement.

## Where the flag lives

| Layer | Behavior |
| --- | --- |
| Storage | `shop_settings.labor_categories[].allows_modifiers` (per labor category / work type) |
| Defaults | RepairPal and Warranty-Other default `false`; Mechanical/Diagnostic/Programming/Fabrication/Courtesy/Comeback default `true` |
| RO line authority | `LaborAuthority::resolveForLine` gates difficulty adjustments, billed-hours overrides, and unit-price overrides |
| Worksheet UI | Legacy Alpine still reads `category.allows_modifiers` for editor affordances |
| Labor Matrix (this PR) | Control **not shown**. Values are preserved on rate and rounding saves |

## What the flag does

When `allows_modifiers` is **true** for the line’s labor category:

- Difficulty / adjustment factors may change entered hours.
- Advisors with pricing authority may override billed hours and hourly rate.

When **false**:

- Adjustment is forced to Normal × 1.0.
- Hours/rate overrides are rejected; policy rate remains.

It does **not** drive billing-class minimums, tax, shop fees, or RO profile stamps.

## Scope note (hybrid Min Hrs / Round To)

| Control | Persistence | Scope |
| --- | --- | --- |
| Rate Type / Hourly Rate | `labor_policies` via matrix rate endpoint | Work type × Billing Class |
| Min Hrs (inline) | `labor_billing_minimums` | Billing Class across **all** work types |
| Round To (inline) | `labor_categories[].rounding_rule` | Selected **work type** only (shown on every class row; editing updates the shared work-type value) |
| Category line floor `minimum_hours` | `labor_categories[].minimum_hours` | Work type; used by `LaborAuthority` for billed hours; **not** the same as billing Min Hrs |
| Allow Modifiers | `labor_categories[].allows_modifiers` | Work type; **enforcement kept**, UI control retired |

There is **no** per-billing-class rounding in the pricing engine. Inventing one would be a financial-logic change and is out of scope for #194.

## Safety recommendation

1. Keep storing and enforcing `allows_modifiers`.
2. Do not mass-flip false → true (or the reverse) in a migration.
3. Future rust / age / brand modifiers are a separate engine with explicit rules — not a revival of this checkbox.
