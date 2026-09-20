# ARK-SMS → ARK V2 Legacy Import Mapping

Controlled one-time / repeatable migration. Not a live sync. Not destructive to non-imported V2 data.

**Before running:** back up both databases. Run `php artisan ark:import-legacy-arksms --audit-schema` against the live legacy connection and reconcile any column drift with this document.

## Legacy connection

| Setting | Value |
|--------|--------|
| Connection name | `arksms_legacy` |
| Env prefix | `ARKSMS_LEGACY_*` |
| Default database name (local) | `arksms` |

Configure in `.env`:

```env
ARKSMS_LEGACY_HOST=127.0.0.1
ARKSMS_LEGACY_PORT=3306
ARKSMS_LEGACY_DATABASE=arksms
ARKSMS_LEGACY_USERNAME=root
ARKSMS_LEGACY_PASSWORD=
```

## Source schema audit (expected ARK-SMS)

> **Verify on your server.** Table names follow Laravel conventions used by ARK-SMS v1. Run `--audit-schema` to dump actual columns.

### `customers`

| Column | Type (typical) | Notes |
|--------|----------------|-------|
| `id` | bigint PK | Preserved as `legacy_arksms_customer_id` |
| `first_name` | string | Required for import |
| `last_name` | string | Required for import |
| `phone` | string nullable | Normalize to E.164-ish digits; duplicates allowed |
| `email` | string nullable | Invalid emails → null + warning |
| `customer_type` | string nullable | Map to V2 `Retail` / `Fleet` / `Commercial` |
| `notes` | text nullable | |
| `created_at` / `updated_at` | timestamps | Preserved on import |
| `deleted_at` | timestamp nullable | **Skip** soft-deleted rows |

**Relationships:** `hasMany` vehicles, repair orders.

**Risks:** split names in one field; empty names; duplicate phones across customers (allowed).

### `vehicles`

| Column | Type (typical) | Notes |
|--------|----------------|-------|
| `id` | bigint PK | `legacy_arksms_vehicle_id` |
| `customer_id` | FK | Required |
| `vin` | string nullable | Missing VIN allowed |
| `license_plate` / `plate` | string nullable | Maps to `plate` |
| `license_plate_state` / `plate_state` | string nullable | |
| `year`, `make`, `model`, `trim` | mixed nullable | |
| `engine`, `transmission` | string nullable | |
| `drivetrain` / `drive_type` | string nullable | Normalize via `VehicleDrivetrainNormalizer` |
| `color` | string nullable | |
| `nickname` | string nullable | |
| `notes` / `public_notes` / `private_notes` | text nullable | Merge into `public_notes` / `private_notes` |
| `mileage` / `odometer` | int nullable | **No V2 column** → append to `private_notes` |
| `deleted_at` | timestamp nullable | Skip |

**Risks:** orphan `customer_id`; invalid year; VIN > 17 chars (truncate/skip with log).

### `repair_orders` (legacy)

| Column | Type (typical) | Notes |
|--------|----------------|-------|
| `id` | bigint PK | V2 `repair_orders.id` (internal FK target) |
| legacy RO number | bigint | V2 `repair_orders.repair_order_id` (shop number, URLs, PartsTech) |
| `customer_id`, `vehicle_id` | FK | Required |
| `status` | string | See status map below |
| `concern` / `concern_summary` / `customer_concern` | text | Maps to `concern_summary` |
| `notes` | text nullable | Append to concern or first concern notes |
| `payment_status` / `paid` / `paid_at` | mixed | Map to V2 `payment_status` + `paid_at` |
| `mileage_in` / `mileage_out` | int nullable | V2 `repair_orders.mileage_in` / `mileage_out` (also `odometer_in` / `odometer_out` aliases) |
| `created_at` | timestamp | V2 `opened_at` (and `created_at` on import) |
| `updated_at` | timestamp | Preserved; **not** used as close date |
| `status_changed_at` | timestamp nullable | Close-date fallback when status is terminal |
| `repair_order_status_logs` | history | **Authoritative close date:** last transition into `closed`, `completed`, `invoiced`, etc. |
| `deleted_at` | timestamp nullable | Skip |

**Financial (header-level, if present):** `subtotal`, `tax`, `total`, `shop_fee` — used only for invoice snapshot / validation, **not** to recalc lines.

**Risks:** unknown legacy status slugs → `closed`. Missing vehicle/customer → skip.

### `repair_order_concerns` / `complaints` / `concerns` (legacy)

Legacy may use a separate table or embed concerns in RO. Importer supports:

1. Dedicated `repair_order_concerns` table, or
2. Single synthetic concern from RO `concern_summary` when no rows exist.

| Column | Maps to V2 |
|--------|------------|
| `id` | `legacy_arksms_concern_id` |
| `repair_order_id` | FK |
| `summary` / `title` | `summary` |
| `notes` | `notes` |
| `disposition` / `approval_status` | `disposition` enum |
| `position` / `sort_order` | `position` |

### `repair_order_lines` / `line_items` / `estimate_lines` (legacy)

| Column | Maps to V2 |
|--------|------------|
| `id` | `legacy_arksms_line_id` |
| `repair_order_id` | FK |
| `concern_id` | `repair_order_concern_id` (fallback: first concern) |
| `type` / `line_type` | `labor` / `part` / `fee` / `note` |
| `description` | `description` |
| `quantity` | `quantity` |
| `unit_price` / `sell` / `price` | `unit_price_cents` (dollars → cents) |
| `cost` / `part_cost` | `part_cost_cents` |
| `subtotal`, `tax`, `shop_fee`, `total` | Preserved cents; `is_overridden=true` |
| `vendor`, `part_number` | optional |

**Financial rule:** copy legacy line money fields verbatim. Do **not** call `EstimateTotalsCalculator::recalculateRepairOrder` during import.

### `invoices` (legacy, optional phase)

| Column | Notes |
|--------|-------|
| `id` | `legacy_arksms_invoice_id` on `estimate_documents` |
| `repair_order_id` | FK |
| `totals` JSON or component columns | Frozen `snapshot_json` with `schema_version: legacy_import` |
| `invoice_number` | `document_number` |

V2 has no separate `invoices` table. Historic invoice truth lives in `estimate_documents.snapshot_json.totals` (authoritative for imported history).

## Target mapping summary

| Legacy source | ARK V2 target | Legacy ID column |
|---------------|---------------|------------------|
| `customers` | `customers` | `legacy_arksms_customer_id` |
| `vehicles` | `vehicles` | `legacy_arksms_vehicle_id` |
| `repair_orders` | `repair_orders` | `repair_order_id` (shop number; child FKs still use `repair_orders.id`) |
| concerns table or RO concern | `repair_order_concerns` | `legacy_arksms_concern_id` |
| line items | `repair_order_lines` | `legacy_arksms_line_id` |
| `invoices` | `estimate_documents` | `legacy_arksms_invoice_id` |

### Skipped entirely

- Payroll, accounting ledgers, GL entries
- Marketing, campaigns, SMS blast tables
- Plugin/addon tables, experimental features
- Users/staff (V2 staff managed separately)
- Encounters (not backfilled; imported ROs have `encounter_id` null)
- Operational events, communications, triggers
- Media attachments (future pass)
- Soft-deleted legacy rows

### Skipped fields (per record)

- Matrix pricing metadata (unless present on line)
- Procurement state (defaults `none`)
- Technician assignment (unless legacy `assigned_technician_id` maps to V2 user by email — **unresolved**, skipped)
- Shop fee rollup synthetic lines (rebuilt only on live recalc)

## Status mapping (verbatim)

Legacy `repair_order_statuses.slug` values are lowercased and mapped through `config/legacy-arksms-import.php` `status_map`. The importer **does not** collapse active workflow to `closed`. Only unknown slugs fall back to `closed` (logged in `unmapped_statuses`).

| Legacy slug (LNP tenant) | ARK V2 `repair_orders.status` |
|--------------------------|-------------------------------|
| `draft` | `draft` |
| `estimate` | `estimate` |
| `waiting_approval` | `awaiting_approval` |
| `approved` | `approved` |
| `waiting_parts` | `waiting_parts` |
| `in_progress` | `in_progress` |
| `quality_check` | `in_progress` |
| `completed` | `ready_pickup` |
| `invoiced` | `ready_pickup` |
| `closed` | `closed` |
| *unmapped* | `closed` + warning |

## Payment mapping

| Legacy | V2 |
|--------|-----|
| `paid`, `paid_in_full`, `1` | `payment_status=paid`, `paid_at` from legacy or `updated_at` |
| else | `unpaid` |

## Line type mapping

| Legacy | V2 |
|--------|-----|
| `labor`, `labour` | `labor` |
| `part`, `parts` | `part` |
| `fee`, `shop_supplies`, `supplies` | `fee` |
| `note`, `notes`, `text` | `note` |
| `sublet`, `service` | `sublet` (customer PDF label: **Service**; staff: **Sublet (Service)**) |
| other | `note` + warning |

## Disposition mapping

| Legacy | V2 |
|--------|-----|
| `approved`, `authorized`, `paid`, `published` | `approved` |
| `deferred`, `deferred_maintenance` | `deferred` |
| `declined`, `rejected` | `declined` |
| default | `recommended` |

## Operational reporting (imported history)

V2 **Sales Closed** uses the same authority as legacy ARK-SMS for imported repair orders:

1. **Close date** — `closed_at` from legacy status logs / invoice timing (`ark:backfill-imported-repair-order-dates`).
2. **Completed scope** — `closed` and `ready_pickup` (legacy `completed`, `invoiced`, `paid`, `closed`).
3. **Opened in range** — imported legacy ROs count toward sales only when **opened_at** is inside the report range (excludes jobs that started in a prior year but closed after import).
4. **Revenue** — legacy invoice `total` when an invoice exists; otherwise approved/published line totals only (not all estimate lines).

Run `php artisan ark:audit-imported-reporting --from=YYYY-MM-DD --to=YYYY-MM-DD` to compare V2 vs legacy. Delta should stay within a few hundred dollars (rounding).

## Transformation rules

1. **Phone:** strip non-digits; keep leading `1` for 11-digit US; store max 32 chars.
2. **Money:** legacy dollars (decimal) → integer cents, half-up.
3. **VIN:** uppercase, trim; empty allowed; invalid length → null + warning.
4. **Drivetrain:** `VehicleDrivetrainNormalizer::normalize()` when available.
5. **Mileage:** `"Legacy odometer: {n}"` appended to vehicle `private_notes` if not already present.
6. **Operational dates:** `opened_at` ← legacy `created_at`. `closed_at` ← last `repair_order_status_logs` transition into a completion slug, then `status_changed_at`, invoice `finalized_at` / `paid_at`, then legacy `updated_at` for terminal statuses only. V2 index shows **Opened** / **Closed**, not misleading `updated_at` for closed ROs.
7. **Timestamps:** preserve legacy `created_at` / `updated_at` on import (subsequent V2 edits may change `updated_at` without changing `closed_at`).
7. **Idempotency:** match on `legacy_arksms_*_id` unique indexes; update only when legacy row `updated_at` is newer.

## Known risks

| Risk | Mitigation |
|------|------------|
| Legacy schema drift | `--audit-schema` before import |
| Unknown legacy status slug | Maps to `closed`, logged in report |
| Recalc changes historic totals | `is_overridden=true`, skip calculator on import |
| Duplicate customers without legacy ID | Only legacy ID prevents dupes; re-import safe |
| Missing concern rows | Synthetic concern from `concern_summary` |
| Invoice totals ≠ sum(lines) | Snapshot stores legacy header totals; warning logged |
| Mileage missing after first import | `php artisan ark:backfill-imported-repair-order-mileage` |
| Sublet lines imported as notes (pre-2026-06 mapping) | `php artisan ark:backfill-imported-sublet-line-types` |
| Technician mapping | Not imported |

## Unresolved questions

1. Exact legacy table names for concerns/lines (config overrides in `config/legacy-arksms-import.php`).
2. Whether legacy uses `repair_orders` vs `orders` table name.
3. Staff user ID mapping for `assigned_technician_id`.
4. Attachment / inspection PDF migration scope.
5. Multi-shop `location_id` filtering if legacy DB contains multiple shops.

## Commands

```bash
# Schema audit (read-only)
php artisan ark:import-legacy-arksms --audit-schema

# Dry run (default)
php artisan ark:import-legacy-arksms --dry-run

# Limited live import
php artisan ark:import-legacy-arksms --force --limit=50

# Single customer
php artisan ark:import-legacy-arksms --force --customer-id=123

# Resume from checkpoint
php artisan ark:import-legacy-arksms --force --resume

# Remove only previously imported rows
php artisan ark:import-legacy-arksms --force --wipe-imported

# Backfill mileage on already-imported repair orders
php artisan ark:backfill-imported-repair-order-mileage --dry-run
php artisan ark:backfill-imported-repair-order-mileage

# Backfill sublet/service line types mis-imported as notes
php artisan ark:backfill-imported-sublet-line-types --dry-run
php artisan ark:backfill-imported-sublet-line-types
```

Report written to: `storage/app/imports/ark-sms/latest-import-report.json`
