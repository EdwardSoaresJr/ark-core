# API & projection backlog — Companion v1

**Rule:** Specs drive API. Backend is **not frozen** for `/api/mobile`.  
**Authority:** Projections read truth · never duplicate financial math client-side.

Existing index: [`../mobile/ark-mobile-projection-v1.md`](../mobile/ark-mobile-projection-v1.md)

---

## P0 endpoints (build with Flutter Sprint 1)

| Need | Spec(s) | Endpoint | Status |
|------|---------|----------|--------|
| Auth + role route | `launch-login.md` | `POST /api/mobile/auth/login` | ✅ `companion` shell |
| Continuity feed | `home-continuity.md` | `GET /api/mobile/continuity` | ✅ + `route` on moments |
| Comms thread list | `conversation-list.md` | `GET /api/mobile/conversations` | ✅ automotive rows |
| Thread + send | `conversation-thread.md` | `GET/POST .../conversations/{id}` | ✅ exists |
| Incoming context | `incoming-call.md` | `GET /api/mobile/incoming-call/context` | ✅ **new** |
| Search unified | `global-search.md` | `GET /api/mobile/search` | ✅ exists |
| RO workspace | `repair-order-workspace.md` | `GET /api/mobile/repair-orders/{id}` | ✅ exists |
| Customer hub | `customer-workspace.md` | `GET /api/mobile/customers/{id}` | ✅ exists |
| Payment | `payment-sheet.md` | `PATCH .../repair-orders/{id}/payment` | ✅ exists |
| Send estimate | `estimate-send-approval.md` | `POST .../send-estimate` | ✅ exists |
| Calls library | `calls-library.md` | `GET /api/mobile/calls` | ✅ **new** |
| Schedule day | `schedule-day.md` | `GET /api/mobile/schedule` | ✅ exists |
| My work (tech) | `my-work.md` | `GET /api/mobile/work` | ✅ exists |
| Inspection | `inspection-item.md` | `GET .../inspection-checklist/items/{item}` | ✅ exists |
| Push registration | `launch-login.md` | `POST /api/mobile/device` | ✅ exists |
| Notifications feed | `notifications-inbox.md` | `GET /api/mobile/notifications` | ✅ + deep_link |
| Phone status | `settings-profile.md` | `companion.phone_status_label` in login shell | ✅ |

---

## Deep link contract (push + continuity)

Every interrupt payload must include routable ids:

```json
{
  "type": "inspection_photo_uploaded",
  "repair_order_id": 1599,
  "inspection_item_id": 42,
  "route": "companion://repair-orders/1599/inspection/items/42"
}
```

| Type | Route target |
|------|--------------|
| `customer_replied` | `conversation-thread` + `conversation_id` |
| `inspection_uploaded` | `inspection-item` + `item_id` |
| `estimate_approved` | `repair-order-workspace` |
| `incoming_call` | `incoming-call` + caller context |
| `missed_call` | `calls-library` + `call_session_id` |

Document in Flutter router · test with [`companion-sprint-1-run-the-shop.md`](../mobile/companion-sprint-1-run-the-shop.md).

---

## Projection fields (repeat everywhere)

| Field | Surfaces |
|-------|----------|
| `customer_name` | All customer rows |
| `vehicle_ymm` · `plate` | Strip · list rows |
| `repair_order_id` · `ro_number` · `lifecycle_label` | Strip · badges |
| `estimate_total` · `approval_posture` | RO · incoming call |
| `balance_due` | Payment · RO |
| `turn` (customer \| shop) | Comms list |
| `deep_link` | Push · continuity · notifications |

---

## P1 (after P0 floor cert)

| Need | Spec |
|------|------|
| Check-in create RO | `check-in-arrival.md` |
| Walk-in intake | `quick-intake.md` |
| Owner pulse metrics | `owner-pulse.md` |
| Shop-wide feed | `shop-feed-owner.md` |
| Warm transfer | `active-call.md` P1 |

---

## Explicit non-goals (Companion v1)

- Parallel SMS inbox API  
- Client-side estimate totals  
- Opportunity/pipeline endpoints  
- CRM disposition enums as required post-call  
