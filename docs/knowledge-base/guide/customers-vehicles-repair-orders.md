---
title: Customers, vehicles, and repair orders
description: The operational records a stock ARK Core shop uses every day.
---

# Customers, vehicles, and repair orders

<!-- source: app/Ark/Operations/RepairOrders/RepairOrderStatus.php -->

Customers and vehicles are shop records. A repair order ties a customer and a vehicle to the work: concerns, lines, inspection, approvals, and the ledger.

## Customers and vehicles

Staff with `customers.manage` and `vehicles.manage` maintain those records. Search and CSV import are part of the stock product described by the repository README. VIN decode uses the NHTSA provider registered on `VehicleIntelligenceManager`. Core does not ship a private vehicle-history database.

The mobile intake API can search and create customers, look up and create vehicles, and open an intake. Those routes are under `/api/mobile/intake` and still require a staff Sanctum token. They are documented as an integration surface, not as a second source of truth.

## Repair order status

The status enum in source, with the labels used when the status catalog has not booted, is:

| Stored value | Label |
| --- | --- |
| `draft` | Draft |
| `estimate` | Building Estimate |
| `waiting_approval` | Waiting Approval |
| `approved` | Approved |
| `waiting_parts` | Waiting Parts |
| `ready_for_work` | Ready for Work |
| `in_progress` | In Progress |
| `quality_check` | Quality Check |
| `completed` | Completed |
| `invoiced` | Invoiced |
| `ready_pickup` | Ready for Pickup |
| `closed` | Closed |

A database-backed status catalog can replace labels and allowed transitions after it boots. The enum above is the code fallback and the set of slugs the application knows. Do not invent additional statuses in a client and expect Core to store them.

Legacy allowed transitions are a match in `RepairOrderStatus::legacyAllowedOperationalTransitions()`. The catalog's `allowedTargetSlugs()` is the authority once the catalog is booted. Closed is the terminal slug when the catalog is not booted.

## Concerns and lines

A concern is a piece of the visit (what the customer came in for, or what the inspection found). Lines on a concern are labor, parts, and fees. Money on those lines is computed on the server.

Editing an open repair order is guarded. Controllers call `ensureOpenForEditing()` before mutations such as evidence upload. A closed repair order is not an open draft.

## Destructive actions

`repair_orders.destructive` is a separate capability from ordinary manage. Treat delete and void-style actions as belonging to that capability rather than to any logged-in staff user.

## Job board and production

The job board, shop display, and technician production views are staff web workflows. They read the same repair orders. They are not a second database.
