# Labor Matrix lifecycle — identity implications

Customer Billing Classes and labor-rate columns are **not** the same record.

| Identity | Storage | Created by | Archive effect | Delete |
| --- | --- | --- | --- | --- |
| Customer Billing Class | `shop_settings.customer_types[]` | Class Settings / Add Billing Class | Hidden from new customer selectors | Only if unreferenced (customers, commercial stamps) |
| Labor matrix column (built-in) | `ConcernBillingPosture` | Platform | Not archived via this UI | Never |
| Labor matrix column (custom) | `custom_labor_billing_classes[]` | Add Labor Column | Hidden from matrix | Only if unreferenced concerns/RO defaults and no linked customer class |
| Work type | `labor_categories[]` (+ OperationClass) | Add Work Type | Hidden from new selections | Only if unreferenced lines/templates/deposit keys; default protected |

**Link:** optional `labor_column_key` on a customer class. Archiving or deleting a customer class never deletes labor rates. Deleting a labor-only column refuses if a customer class still links to it.

**Manage UI:** “Customer-only & archived” lists customer-only + archived customer classes and labor-only columns. Labor-linked active classes edit only via the Class Settings gear.
