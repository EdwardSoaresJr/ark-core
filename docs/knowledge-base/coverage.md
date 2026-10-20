---
title: Coverage matrix
description: What the ARK Core knowledge base documents, what is only partial, and what is intentionally absent.
---

# Coverage matrix

This matrix is about the knowledge base, not about a promise to build missing product features. **Documented** means a page describes behavior that was checked against source. **Partial** means the page covers the shape and names a gap. **Missing** means this site does not yet have a reliable page, or the product capability does not exist and is labeled as such.

No empty placeholder pages were added to fill the outline.

## Getting started

| Topic | State |
| --- | --- |
| What Core is and is not | Documented |
| License pointer | Documented |
| Requirements | Documented |
| Docker Compose install | Documented |
| Host PHP install | Documented |
| First-run `/setup` | Documented |
| Environment, database, storage, mail, queues, scheduler | Documented |
| Local development and tests | Documented |
| Production shape | Documented. Vendor-specific walkthrough remains `docs/installation/vultr.md` in the repo. |
| Update, backup, restore | Documented |
| Troubleshooting | Documented |

## Shop guide

| Topic | State |
| --- | --- |
| Staff roles and default capabilities | Documented |
| Workstations and station tokens | Documented |
| Customers, vehicles, repair-order statuses | Documented |
| Estimates, portal, inspections, PDFs | Documented at workflow level. Individual Blade screens are not a click-by-click manual. |
| Ledger, scheduling, reports | Documented. Report formulas are not restated field by field. |
| Communications versus Hosted services | Documented |

## Architecture

| Topic | State |
| --- | --- |
| Request path, directories, container bindings | Documented |
| Repair order, inspection, money, files, frontend | Documented |
| Auth, CSRF, rate limits, logging | Documented |
| Per-table entity relationship diagram | Missing. The schema is large. Cite migrations instead of an invented diagram. |

## Extending

| Topic | State |
| --- | --- |
| No plugin manager | Documented |
| Providers, container, events, routes, views, commands, jobs, migrations, policies | Documented |
| Composer package pattern | Documented |
| Plugin-ecosystem gaps | Documented as gaps |
| Dashboard widgets, themes, report packs | Not documented as features. Listed as proposed. |

## Integrations and mobile

| Topic | State |
| --- | --- |
| Four API prefixes and auth | Documented |
| Repair-order read payload keys | Documented for the top-level projection |
| Evidence and inspection photo limits | Documented |
| Capability matrix | Documented |
| Mobile workflows possible today | Documented |
| Every mobile JSON key | Partial. Controllers and projections remain the authority. |
| OpenAPI file | Missing in the product and in this site |
| Desk and station field-level payloads | Partial. Routes are listed. Response bodies are not fully copied. |

## Reference

| Topic | State |
| --- | --- |
| Environment names an installer needs | Documented |
| Route-file map | Documented |
| Role capability table | Documented from `config/ark-permissions.php` |
| Safe commands, schedule, jobs, listener events | Documented |
| Full command catalog with every flag | Partial. Import and backfill commands are named as a class directory, not copied. |
| Every Eloquent column | Missing on purpose |

## Publication

The site builds locally. It is not deployed. See [Publication readiness](/publication-readiness).
