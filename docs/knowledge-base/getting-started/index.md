---
title: Getting started
description: What a stock ARK Core install is, what it includes, and what it deliberately leaves out.
---

# Getting started

<!-- source: README.md -->

ARK Core is the shop-management system. A shop runs it on its own server. It is free under the GNU Affero General Public License version 3 only. It is not a trial, a time limit, or a teaser for a hosted service.

**Copyright (C) 2026 Edward Soares Jr.** The license text is `LICENSE` in the repository root. A plain-language summary, which is not legal advice, is on the [license page](/contributing/license).

One Core installation is one shop. Core does not use a shared-database multi-tenant model.

## What a stock install includes

A stock install is this repository, brought up with Docker Compose or a compatible PHP environment, then finished in the browser at `/setup`. No ARK Shop account is required.

The shop work that runs on that install includes:

- Customer and vehicle records, search, CSV import, and NHTSA VIN decode
- Repair orders with concerns, labor, parts, fees, and notes
- Estimates with server-side totals, tax, and customer authorization
- Digital vehicle inspections, including templates, photos, and prior-visit history
- Scheduling, including appointment bays, when the appointments surface is enabled
- Job board, shop display, and the daily production view
- Technician workflows and a time clock
- Parts and labor lines, parts pricing matrices, and labor rates
- Engine-oil service recorded on the repair order
- Dashboard, day review, technician production, and operational reports
- A ledger for payments the shop already took (cash, check, or a card charged outside ARK)
- Documents, estimate and invoice PDFs, and printing settings
- A customer portal for estimates, approvals, inspections, photos, and documents
- Staff, roles, workstations, and shop configuration
- Dragon, using a model provider you configure. No ARK-hosted model is included
- HTTP contracts for separate client apps at `/api/mobile`, `/api/desk`, `/api/tech`, and `/api/station`

The customer portal can show an amount owed. Showing that amount is not card processing.

AllData and ProDemand buttons open the vendor site and copy the VIN. The shop uses its own login. Core includes a labor-guide import interface. Licensed labor-guide datasets are not in this repository.

Financial totals stay on the server. The repair-order screen displays them. It does not calculate them again in the browser.

## What a stock install does not include

ARK Shop is a private commercial product. It is built from a pinned Core release plus services ARK operates. You do not need it to install or run Core.

These capabilities belong to ARK Shop, not to a stock Core install:

| Capability | Stock Core |
| --- | --- |
| Customer email delivery | Not included. Core can be configured with Laravel mail for mail the application itself sends. Customer messaging as an ARK-operated service is ARK Shop. |
| Customer texting | Not included. |
| Hosted phone service | Not included. |
| In-app card capture | Not included. |
| PartsTech shop catalog access | Not included. Partner API materials are not published in this repository. |

Also not in this repository:

- Desk, Tech, Companion, and similar client applications. Core exposes the HTTP contracts only.
- The shop website editor. Core can keep shop identity and lead records.
- Licensed automotive datasets
- Production credentials or live shop data
- An ARK-hosted Dragon model
- Managed infrastructure products that are still being built, including ARK Backup, ARK Storage, ARK Data, and moving a shop between self-hosted Core and ARK Shop

Core includes screens for communications, conversations, calls, parts catalogs, and payments. Those screens are the shop workflow. When the matching Hosted service is not connected, Core says so and the rest of the shop keeps running. A settings screen is not a self-hosted Twilio, Square, or PartsTech product.

## Where to go next

| Goal | Page |
| --- | --- |
| Install on a laptop or server | [Requirements](/getting-started/requirements) |
| Understand the shop workflows | [Shop guide](/guide/) |
| Change or extend the code | [Extending ARK Core](/extending/) |
| Call Core from another program | [Building integrations](/integrations/) |

<!-- source: docs/PRODUCT_BOUNDARY.md -->
