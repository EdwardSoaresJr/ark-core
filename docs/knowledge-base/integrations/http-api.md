---
title: HTTP API surfaces
description: Routes, formats, errors, and filtering behavior of the ARK Core client APIs.
---

# HTTP API surfaces

<!-- source: routes/api.php -->
<!-- source: app/Ark/Operations/RepairOrders/RepairOrder.php -->
<!-- source: app/Ark/Mobile/MobileRepairOrderProjection.php -->

Base URL in examples is `https://shop.example.test`. Send `Accept: application/json`. API exceptions render as JSON when the path starts with `api/` or the client expects JSON.

## Formats

| Topic | Behavior |
| --- | --- |
| Success | JSON objects. Status 200 unless a controller sets another code. Evidence create returns 201. |
| Validation | HTTP 422, Laravel `message` and `errors`. |
| Unauthenticated | HTTP 401, `{"message":"Unauthenticated."}` for the station middleware. Staff Sanctum uses Laravel's authentication exception. |
| Forbidden | HTTP 403. |
| Not found | HTTP 404 when a scoped repair order, concern, or photo does not belong to the parent. |
| Pagination | Not a shared envelope. Read the controller. Many responses are a single projection, not a page. |
| Filtering | Query filters exist only where a controller reads them. There is no generic filter language. |
| Versions | None in the path. |

## Mobile repair order

`GET /api/mobile/repair-orders/{repairOrder}` returns:

```json
{
  "repair_order": {
    "id": 1001,
    "repair_order_id": 1001,
    "status": "in_progress",
    "estimate_version": 3,
    "status_label": "In Progress",
    "customer": { "id": 1, "name": "Jordan Lee" },
    "vehicle": { "id": 1, "label": "2018 Example", "vin": "1HGCM82633A004352", "plate": "EXAMPLE" }
  },
  "workspace": {}
}
```

That sample keeps keys that `MobileRepairOrderProjection::forRepairOrder()` sets: `id`, `repair_order_id`, `status`, `estimate_version`, `status_label`, `customer`, `vehicle`, plus `concerns`, `approved_work`, `estimate`, `recent_findings`, `communications`, and `quick_actions`. `estimate` is `null` for a technician viewer. `workspace` comes from `RepairOrderWorkspaceProjection` and is not expanded here. Numeric ids in the sample are illustrative. `id` and `repair_order_id` are the integer `repair_order_id` column. Route model binding uses that column (`getRouteKeyName`), not the table primary key.

Other mobile routes on the same auth group include concerns, lines, findings, inspection checklist read and item update, inspection photo download, evidence upload, conversations, schedule, appointments, customer and vehicle workspace, intake, search, and VIN decode. The route list is the file. The matrix classifies them.

### Estimate version on changes

Every mobile response about one repair order carries its current estimate version in the `X-Ark-Estimate-Version` header, including changes whose JSON body does not include it. A client sends the last version it saw as `opened_estimate_version` (body, or query string for `DELETE`) with every repair-order change. If someone changed the estimate since, the change is refused with `409` and `{"conflict": true, "message", "estimate_version", "changed_by", "changed_at"}`; the client reloads the repair order before trying again. A change sent without `opened_estimate_version` is still accepted, for clients released before this contract, and is not checked for conflicts.

## Desk

Desk is a smaller advisor surface: login, logout, `GET /me`, `GET /work`, workstation binding, call show and handle, tasks, and Dragon chat. It is not a second copy of the mobile repair-order API.

## Tech

Tech login, `GET /me/work`, repair-order show, inspection show, inspection item patch, voice utterance and confirm, and Dragon rewrite. The item patch accepts `status` (`good`, `monitor`, `needs_attention`, `failed`, `na`), `note`, measurements, and an optional `photo` file up to 20480 KB on that request. The inspection evidence store used by the web UI allows a larger ceiling. Do not assume the Tech request and the web upload share one limit.

## Station

`GET /me`, `GET /dashboard`, attention nudge, Dragon chat, repair-order show, call show and handle, settings, and tasks. Methods the station middleware does not allow return 405.

## Dragon agent

`POST /api/dragon-agent/chat` requires a staff Sanctum token. With `DRAGON_PROVIDER=none` the assistant has no model. A client should handle that as "not configured," not as an empty shop.

## Customers and vehicles

`GET /api/mobile/customers/{customer}` and `GET /api/mobile/vehicles/{vehicle}` return workspace projections for one record. Intake adds search and create under `/api/mobile/intake`. There is no unauthenticated customer directory.

## VIN decode

`POST /api/mobile/tools/vin-decode` is a staff route. It uses the NHTSA provider behind `VehicleIntelligenceManager`. It does not require a third-party key in stock Core.
