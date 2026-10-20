---
title: Capability matrix
description: Which ARK Core integration capabilities exist, which are incomplete, and which are absent.
---

# Capability matrix

<!-- source: routes/api.php -->

Status words mean:

| Status | Meaning |
| --- | --- |
| Available | A route or binding exists and this site describes how to call it. |
| Partial | The route exists, but the workflow a product would expect is incomplete or split across surfaces. |
| Internal | Implemented for the staff UI or for ARK-operated ingress. Not a stable client API. |
| Missing | No such contract in this repository. |

| Capability | Status | Where |
| --- | --- | --- |
| Staff bearer login for mobile, desk, and tech | Available | `POST /api/{mobile,desk,tech}/auth/login` |
| Current user | Available | `GET /api/mobile/me`, `GET /api/desk/me` |
| Repair order read | Available | Mobile and station show routes. Tech has its own show. |
| Repair order status patch | Available | `PATCH /api/mobile/repair-orders/{repairOrder}/status` |
| Concerns and lines write | Available | Mobile routes. Still subject to capabilities and open-for-edit rules. |
| Customer and vehicle read | Available | Mobile workspace routes |
| Customer and vehicle create | Partial | Mobile intake routes. Not a general CRUD API outside intake. |
| VIN decode | Available | `POST /api/mobile/tools/vin-decode` |
| Inspection checklist read and item update | Available | Mobile checklist routes and Tech item patch |
| Inspection photo download | Available | Mobile photo show, `local` disk |
| Inspection photo upload | Partial | Tech item patch and the staff web form. No mobile JSON upload dedicated to inspection photos. |
| Evidence upload (concern or repair order) | Available | `POST /api/mobile/.../evidence` |
| Estimate money in API | Partial | Present for non-technician mobile viewers. Not a public pricing API. Server remains the authority. |
| Customer portal automation | Internal | Browser and token links. Do not treat as a partner API. |
| Conversations read and post | Partial | Mobile routes exist. Delivery to the customer's phone is Hosted texting, not guaranteed by the route. |
| Schedule and appointments | Available | Mobile schedule, store, and status routes when appointments are enabled. |
| Payments ledger writes | Partial | Mobile payment, deposit, refund, and void routes. Not card capture. |
| Card capture | Missing in stock Core | ARK Shop |
| Outbound webhooks you subscribe to | Missing | No registry |
| Cloud ingress webhooks | Internal | `/webhooks/cloud/*` |
| OAuth scopes | Missing | Tokens carry the user, not scopes |
| API version header or `/v1` | Missing | Unversioned |
| Global API rate limit | Missing | Add one at your gateway if you need it |
| OpenAPI document | Missing | This site is the description. The source routes are the authority. |
| Station device API | Available | Separate token. Narrow route list. |
| Parts catalog partner API | Missing in this repository | ARK Shop. Not documented here. |
| Push device registration | Partial | `POST /api/mobile/device` exists. Delivery depends on `PushTransport` and Firebase configuration. Stock binding is Firebase. A shop without that configuration will not receive pushes. |

When a cell says partial, build the product against the limitation. Do not fill the gap in the client by writing shop totals or by screen-scraping the staff UI.
