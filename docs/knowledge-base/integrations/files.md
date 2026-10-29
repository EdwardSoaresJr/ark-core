---
title: Files, evidence, and limits
description: How ARK Core accepts inspection photos and repair-order evidence over HTTP.
---

# Files, evidence, and limits

<!-- source: app/Ark/Operations/Evidence/EvidenceStore.php -->

## Repair-order evidence

`POST /api/mobile/repair-orders/{repairOrder}/evidence` requires `repair_orders.manage` and an open repair order. The body is multipart form data.

| Field | Rule |
| --- | --- |
| `file` | Required. MIME one of JPEG, PNG, WebP, MP4, WebM, QuickTime, PDF. Max 102400 KB. |
| `attachable_kind` | `concern` or `repair_order` |
| `attachable_id` | Integer. For `repair_order`, the server uses the repair order in the URL. |
| `caption` | Optional string, max 500 |
| `source` | Optional `camera`, `upload`, `migration`, or `system` |
| `as_primary` | Optional boolean |

```bash
curl -s -X POST "https://shop.example.test/api/mobile/repair-orders/1001/evidence" \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer example-token-value-not-real' \
  -F 'file=@brake-pad.jpg;type=image/jpeg' \
  -F 'attachable_kind=concern' \
  -F 'attachable_id=15' \
  -F 'source=camera' \
  -F 'caption=Driver outer pad'
```

Success is HTTP 201 and `{"evidence": ... }` from `EvidenceProjection`. The example filename is fictional.

`GET /api/mobile/repair-orders/{repairOrder}/evidence/{evidence}` returns one evidence projection, not necessarily the raw bytes. Confirm the controller before assuming a file stream.

## Inspection photos

`GET /api/mobile/repair-orders/{repairOrder}/inspection-photos/{photo}` streams bytes from the `local` disk after `MobileStaffAccess::canViewRepairOrder()`. Missing files are 404. The content type is the type stored on the photo row.

There is no `POST` inspection-photo route on `/api/mobile` in `routes/api.php`. Capturing an inspection photo from a client today is either:

- `PATCH /api/tech/repair-orders/{repairOrder}/inspection-items/{item}` with a `photo` file, for a technician token, or
- the staff web form handled by `RepairOrderInspectionPhotoStoreController`, which redirects and is not a JSON API

Inspection media MIME types are JPEG, PNG, WebP, MP4, WebM, and QuickTime. PDF is evidence, not an inspection photo. The inspection store ceiling is 102400 KB. The Tech item patch validates `photo` as `file|max:20480` before that store runs, so the Tech request is the tighter limit.

## Conversation attachments

`GET /api/mobile/conversations/{conversation}/messages/{message}/attachments/{attachment}` is a staff route for an attachment already on a message. It is not a general file host.

## Limits that are easy to miss

- No application-wide API throttle is enabled in `bootstrap/app.php`.
- Portal token routes are throttled. They are not the staff API.
- Sanctum tokens do not expire by configuration.
- Technician tokens do not receive estimate money in the mobile repair-order payload.
