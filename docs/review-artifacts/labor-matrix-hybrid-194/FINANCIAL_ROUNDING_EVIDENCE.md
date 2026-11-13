# Financial evidence — Retail vs RepairPal rounding (#194)

Source hours identical: **1.01**. Adjustment: Normal. Work type: Mechanical.

| Billing class | Round To | Billed hours | Rate cents | Authoritative line cents (`hours × rate`) |
| --- | --- | ---: | ---: | ---: |
| Retail (`customer_pay`) | quarter | 1.25 | 16500 | **20625** |
| RepairPal | tenth | 1.10 | 15000 | **16500** |

## Coverage

- `LaborAuthorityTest` → `stamped profile schema 3 applies per billing-class rounding`
- `LaborAuthorityTest` → `shop labor_rounding matrix applies independent Retail vs RepairPal rules for new work`
- `LaborAuthorityTest` → `stamped schema 3 rounding is unchanged when shop labor_rounding later diverges`
- `LaborWorkTypeMatrixSettingsTest` → `existing open RO keeps stamped rates for new labor after settings change`

Open RO stamped profiles (schema ≥2 rates / schema ≥3 rounding) stay frozen when shop settings change. Approvals, invoices, and documents continue to read stamped line money — settings edits apply to new ROs only.
