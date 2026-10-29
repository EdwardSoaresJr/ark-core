---
title: Storage and domain modules
description: Filesystem disks and the main ARK Core code modules.
---

# Storage and domain modules

<!-- source: config/filesystems.php -->

## Disks

| Disk | Root | Notes |
| --- | --- | --- |
| `local` | `storage/app/private` | Default disk. Mobile inspection photo streaming reads this disk by name. |
| `public` | `storage/app/public` | URL under `APP_URL/storage` after `storage:link`. |
| `s3` | S3-compatible | Optional. `ARK_MEDIA_DISK` defaults to `local`. |

Durable shop files live under `storage/` and must be backed up with MySQL and `APP_KEY`. Redis is not durable shop truth.

## Modules

| Namespace | Responsibility |
| --- | --- |
| `App\Ark\Install` | First-run state and installer middleware |
| `App\Ark\Operations\RepairOrders` | Repair orders, concerns, status |
| `App\Ark\Operations\Inspections` | Inspections, checklist, inspection media |
| `App\Ark\Operations\Evidence` | Evidence files attached to a concern or repair order |
| `App\Ark\Operations\Financial` | Ledger and money presentation |
| `App\Ark\Operations\Approvals` | Customer authorization records |
| `App\Ark\Operations\Messaging` | Conversation messages and the inbound message event |
| `App\Ark\Operations\Appointments` | Scheduling |
| `App\Ark\Operations\Documents` | PDFs |
| `App\Ark\Mobile` | `/api/mobile` controllers and projections |
| `App\Ark\Desk` | `/api/desk` |
| `App\Ark\Tech` | `/api/tech` |
| `App\Ark\Station` | Station tokens and `/api/station` |
| `App\Ark\Dragon` | Model assistant. Provider defaults to `none`. |
| `App\Ark\Platform` | ARK-operated service edges |
| `App\Ark\Runtime\Authorization` | Roles and capabilities |
| `App\Models\User` | Staff and customer user record |

Models are PHP classes in those namespaces. The schema is `database/migrations`. This page does not duplicate three hundred migration files. Open the migration that created the table you are reading before you write a package that depends on a column.

## Common errors

| Symptom | Look at |
| --- | --- |
| Redirect loop on a new install | [Troubleshooting](/getting-started/troubleshooting), `ark:install-status` |
| 401 on `/api/station` | Station token, not a staff Sanctum token |
| 403 on evidence upload | `repair_orders.manage` |
| 409 on an estimate save | Another editor holds the estimate lock |
| 422 on login | Validation body. Includes bad password, disabled user, and wrong product role. |
| PDF exception | `PDF_CHROME_PATH` and the Chromium binary |
| Jobs stuck | `QUEUE_CONNECTION` and whether Horizon or a worker is running |
