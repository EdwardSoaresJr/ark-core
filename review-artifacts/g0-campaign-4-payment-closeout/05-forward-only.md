# Forward-only settings verification

**Result:** Pass (application-path on staging alpha). Live settings restored.

## Method

On tenant `alpha`, mutate live `ShopSettings` (same persistence model as Financial Rules Save), recalculate RO #20, create synthetic RO #21, restore settings.

Mutations applied together:

- Shop fees: enable percent **10.000**
- Tax: enable **8.250%**
- Default labor rate: **20000¢** ($200/hr)

## RO #20 (historical)

| Checkpoint | fees line sum | approved total | issued invoice |
| --- | ---: | ---: | ---: |
| Before | 0 | 25950 | 25950 |
| After live mutation + recalculate | 0 | 25950 | 25950 |
| After restore + recalculate | 0 | 25950 | 25950 |

Stamped profile remained `enabled:false` / `rate:0.000`. Unauthorized +$108 scope still excluded from invoice.

## New RO #21 (post-policy)

| Field | Value |
| --- | --- |
| Labor line | 15000¢ |
| Stamp | `enabled:true`, `mode:percent`, `rate:10.000` |
| Fees | **1500¢** |
| Approved total | **16500¢** |

## Restore

Live settings restored to pre-drill: fees off, tax off, labor 16500¢.

## Scope note

Billing-class / parts-matrix UI toggles were not exhaustively walked in this pass; Shop Fees + tax + labor-rate stamp preference was proven end-to-end. Existing automated coverage in `ShopFeeFlatDollarTest` complements this for matrix/flat fee forward-only.
