---
title: What a mobile client can do today
description: Mobile workflows that exist in ARK Core and workflows that do not.
---

# What a mobile client can do today

<!-- source: routes/api.php -->

Possible means a route exists and a staff token that holds the right role can call it. It does not mean the Hosted service behind a conversation or a payment will run on a stock install.

## Possible today

| Workflow | Route family | Limit |
| --- | --- | --- |
| Sign in and out | `/api/mobile/auth` | Staff only. Token does not expire by time. |
| Load the signed-in person | `GET /api/mobile/me` | Shell shape includes presenter fields. |
| Open a repair order | `GET /api/mobile/repair-orders/{repairOrder}` | `{repairOrder}` is the integer `repair_order_id`. Technicians do not receive estimate money. |
| Add and edit concerns and lines | Mobile concern and line routes | Open repair orders. Capability checks inside the controllers. |
| Change status, technician, deposit, recorded payment, refund, void | Mobile patch and delete routes | Ledger writes record money already taken. They do not capture a card. |
| Read and update inspection checklist items | Mobile inspection checklist routes | Status values are the checklist enum. |
| Download an inspection photo | `GET .../inspection-photos/{photo}` | Bytes from the `local` disk. |
| Upload evidence onto a concern or the repair order | `POST .../evidence` | Needs `repair_orders.manage`. JPEG, PNG, WebP, video, or PDF. |
| Record an inspection item, including a photo | `PATCH /api/tech/.../inspection-items/{item}` | Technician token. Photo max 20480 KB on this request. |
| Search and create during intake | `/api/mobile/intake` | Intake flow, not a general CRM API. |
| Decode a VIN | `POST /api/mobile/tools/vin-decode` | NHTSA provider. |
| Read a customer or vehicle workspace | Mobile customer and vehicle routes | One record per call. |
| Schedule | `/api/mobile/schedule` and appointment routes | Appointments feature must be enabled or the shop surface will reject the work. |
| Conversations | `/api/mobile/conversations` | Posting a message hits Core. Delivery to a customer's phone is not a stock SMS gateway. |
| Search, attention, notifications | Mobile index routes | Projection payloads. Not a guaranteed page size. |

## Not possible today

| Workflow | Why |
| --- | --- |
| A customer-facing mobile app using these tokens | Customer role is rejected at login. The portal is a separate browser flow. |
| Upload an inspection photo through `/api/mobile` | No such POST route. Use Tech or the staff web form. |
| Card capture inside the app | ARK Shop. |
| Reliable customer SMS or hosted voice | ARK Shop. The routes exist. The stock transports are platform or not-configured. |
| Offline sync with conflict-free merge | No sync API. |
| A frozen OpenAPI file | Not in the repository. |
| Calling station routes with a staff token | Station middleware rejects them. |
| Assuming web routes will return JSON if you drop a bearer token on them | Staff pages are session and CSRF. |

## Error handling

| HTTP | Client behavior |
| --- | --- |
| 401 | Send the user to login. Delete the stored token. |
| 403 | The user cannot do that. Do not retry. |
| 404 | The id is wrong or the child row is not on that repair order. |
| 409 | Estimate conflict. Reload the repair order. Do not replay the write blindly. |
| 422 | Show field errors from `errors`. |
| 5xx | Retry later with backoff. Do not invent a successful local write. |

## Photographs and documents

A third-party client can download inspection photos and upload evidence with the limits on the [files page](/integrations/files). It cannot yet do every inspection photo workflow through the mobile prefix alone. That split is the current reality. Closing it would be a Core route change, not a client convention.
