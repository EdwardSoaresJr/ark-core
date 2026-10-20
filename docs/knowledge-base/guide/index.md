---
title: Using the shop
description: How the shop-facing features in ARK Core fit together, and which ones are only screens.
---

# Using the shop

<!-- source: docs/PRODUCT_BOUNDARY.md -->

This guide describes workflows a stock Core install actually implements. It is an operator map, not a screen-by-screen manual of every button. Where a screen exists but the matching service does not ship with Core, the page says so.

Staff sign in through the web login (`routes/auth.php`) with the email and password created at setup or added later under staff. Customer portal access is a different guard. Customers do not use the staff shell.

## Map

| Area | What Core does | Page |
| --- | --- | --- |
| Shop and staff | Settings, roles, workstations | [Shop setup and staff](/guide/shop-and-staff) |
| Front counter | Customers, vehicles, repair orders | [Customers, vehicles, and repair orders](/guide/customers-vehicles-repair-orders) |
| Selling the work | Estimates, inspections, PDFs, portal | [Estimates, inspections, and documents](/guide/estimates-inspections-documents) |
| Running the day | Ledger, schedule, reports | [Money, scheduling, and reports](/guide/money-scheduling-reports) |
| Outside the building | Messaging screens, catalogs, card capture | [Communications and connected services](/guide/communications-and-integrations) |

Permissions are capabilities on roles, stored with `spatie/laravel-permission`. The role and capability lists are in [Permissions and roles](/reference/permissions). A technician does not receive the same repair-order money payload as an advisor, including on the mobile API.

## One shop

Workstations are places in that shop. They are not separate tenants. There is no `shop_id` column you set to turn one database into many shops.
