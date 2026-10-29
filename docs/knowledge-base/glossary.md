---
title: Glossary
description: Domain words used in ARK Core documentation.
---

# Glossary

<!-- source: app/Ark/Operations/RepairOrders/RepairOrderStatus.php -->

| Term | Meaning in Core |
| --- | --- |
| Repair order | The shop's record of one visit: customer, vehicle, concerns, lines, inspection, approvals, and ledger. The public number is the integer `repair_order_id`. |
| Concern | A unit of work on a repair order. Lines and findings attach to it. |
| Line | Labor, parts, or a fee on a concern. Amounts are computed on the server. |
| Estimate | The priced proposal for the work. Customers authorize it. Totals are not recalculated in the browser. |
| Authorization | The recorded customer decision on the estimate. Stored through the approvals module. |
| Ledger | Rows for payments, deposits, refunds, and voids the shop recorded. Not a card capture. |
| DVI | Digital vehicle inspection. The inspection module on a repair order. |
| Checklist status | `good`, `monitor`, `needs_attention`, `failed`, or `na` on an inspection item. |
| Evidence | A file attached to a concern or the repair order. Separate from an inspection-item photo, even when both are images. |
| Portal | The customer browser surface for an estimate, inspection, and documents. Not the staff API. |
| Workstation | A place in the one shop. Not a tenant. |
| Station token | A device credential for `/api/station`. Not a staff password. |
| Capability | A permission string such as `repair_orders.manage`, granted through a role. |
| ARK Shop | The private commercial shop-management product around Core. Not required to run this repository. |
| ARK SaaS | Former name of ARK Shop. Not a separate product. |
| Dragon | The model assistant shipped in Core. `DRAGON_PROVIDER` defaults to `none`. |
| Stock install | This repository, installed with Compose or PHP and `/setup`, without an ARK Shop account. |

<!-- source: app/Ark/Operations/Inspections/InspectionChecklistStatus.php -->
