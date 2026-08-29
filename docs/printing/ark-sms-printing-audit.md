# ARK-SMS Printing Audit

**Status:** `AUDITED` (production server source + config; tenant DB values not sampled — multi-tenant)

**Audited on:** 2026-06-06  
**Auditor:** remote session via `root@24.144.81.19`  
**ARK-SMS deploy path:** `/var/www/sites/ark-sms/production/current`  
**Release:** `20260531023539`  
**V1 reference doc (on server):** `docs/ARK-SMS-PRINTING-QZ-KEY-TAGS.md` (240 lines — canonical operational detail)

---

## Blocking questions (answered)

| # | Question | Answer |
|---|----------|--------|
| 1 | **Where are the current templates?** | Blade PDF templates under `resources/views/tenant/admin/repair_orders/pdf/`: `key-tag.blade.php`, `oil-change-sticker.blade.php`, plus block partials in `key_tag_blocks/` and `oil_change_sticker_blocks/`. Layout is **DB-driven** via `key_tag_templates` + `key_tag_template_blocks` and `oil_change_sticker_templates` + `oil_change_sticker_template_blocks` (tenant DB). Services: `KeyTagTemplateService`, `OilChangeStickerTemplateService`. |
| 2 | **How are dimensions defined?** | `config/printing.php` defaults: **62.0 × 38.1 mm** (`key_tag_qz_page`). Tenant overrides in `static_options`: `qz_key_tag_label_width_mm`, `qz_key_tag_label_height_mm` (and oil sticker equivalents, with inherit-from-key-tag fallback). Per-location suffix keys `:location_id`. Snappy PDF options built in `TenantPrintingSettings::keyTagSnappyPdfOptions()` / `oilStickerSnappyPdfOptions()`. Optional reference lock: `ql_label_reference_mm`, `ql_key_tag_lock_reference_px` at 203/300 DPI. |
| 3 | **How are printer names stored?** | Tenant DB `static_options` via `App\Support\TenantPrintingSettings` — **not** `.env`. Keys: `qz_printing_key_tag_printer`, `qz_printing_oil_sticker_printer` (falls back to key tag), `qz_printing_ro_printer`. Per-location overrides: same keys with `:location_id` suffix. Config fallback only: `config('printing.key_tag_printer')` default **`Brother QL-800`**. |
| 4 | **How are oil change mileages calculated?** | `App\Support\Printing\OilChangeStickerPrintContext::fromRepairOrder()`. Current mileage: `vehicle_mileage_out` else `vehicle_mileage_in`. Next mileage: **current + interval** where interval = `config('vehicle_maintenance_intervals.intervals.oil.interval')` default **5000** miles. Calendar due: anchor `mileage_out_at` else RO date + `oil_sticker_next_due_months` (config default **6**, tenant override `static_options` `oil_change_sticker_next_due_months`). Combined line: `Due: {next_mi} or {date}`. Oil type: `OilChangeStickerOilTypeResolver`. |
| 5 | **How are QZ certificates handled?** | `config/printing.php` → `qz.certificate_path`, `qz.private_key_path` from env `QZ_CERTIFICATE_PATH`, `QZ_PRIVATE_KEY_PATH`. Signing: `App\Support\QzTraySigning`, `QzTraySignController::signMessage`. Routes: `POST /app/api/qz/sign-message`, `POST /app/qz/sign`. Health: `GET /app/api/printing/health`, `GET /app/api/qz/sign-health`. **Production note:** shared `.env` references `/home/master/applications/production/public_html/qz/*.pem` — **files not present** on audited server path; verify live signing on advisor workstations or update paths. |
| 6 | **What settings already exist?** | See [Shop / printer settings](#3-shop--printer-settings-persistence) below. Settings UI: Repair Order Settings, Location edit → QZ, QZ Wizard (`QzWizardController`), key tag / oil sticker layout editors. |

**Ready for V2 implementation planning:** Yes — port from audited paths; **do not write QZ code until migration plan is scoped to V2 authority model** (single shop, `shop_settings` not `static_options`).

---

## 1. Architecture summary

```
Browser (arkPrintDocument / arkPrintPDF)
  → GET PDF from server (KeyTagPrintController / OilChangeStickerPrintController)
  → QZ Tray websocket (qz-tray.js, lazy-loaded except QZ wizard)
  → Brother QL-800 (name from TenantPrintingSettings)
```

- **Payload:** Server-rendered **PDF** (Snappy/wkhtml), not client-computed label data.
- **QL path (default):** PDF sent to QZ as `pixel/pdf`, `rasterize: false` — driver owns page size.
- **Optional:** `PRINTING_QL_FORCE_RASTER=true` → PDF.js → PNG → `pixel/image`.
- **Single JS entry:** `resources/views/components/print-helpers.blade.php` (~3400 lines) + `public/js/ark/ark-qz-key-tag.js`.

---

## 2. QZ Tray integration

| Item | ARK-SMS location |
|------|------------------|
| QZ JS | `public/vendor/qz/qz-tray.js` |
| QL helpers | `public/js/ark/ark-qz-key-tag.js` (`window.ArkQzKeyTag`) |
| Print orchestration | `resources/views/components/print-helpers.blade.php` |
| Signing | `app/Support/QzTraySigning.php` |
| Sign controller | `app/Http/Controllers/Tenant/Admin/QzTraySignController.php` |
| Signing health | `app/Services/Printing/QzSigningHealthService.php` |
| Print metrics / guards | `app/Support/QzPrintMetrics.php`, `QzPrintDuplicateGuard.php`, `QzPrintIdempotencyGuard.php` |
| Setup wizard | `app/Http/Controllers/Tenant/Admin/QzWizardController.php`, `app/Services/Setup/QzSetupService.php` |

### Routes (tenant prefix `/app`)

| Method | URI | Name | Purpose |
|--------|-----|------|---------|
| POST | `api/qz/sign-message` | `tenant.admin.api.qz.sign_message` | Sign QZ payload |
| POST | `qz/sign` | `tenant.admin.qz.sign` | Alias sign endpoint |
| GET | `api/qz/sign-health` | `tenant.admin.qz.sign_health` | Signing health |
| GET | `api/printing/health` | `tenant.admin.api.printing.health` | Printing + cert health |
| GET | `api/printing/printer?type=` | `tenant.admin.api.printing.printer` | Resolve printer name + mm + DPI |
| GET | `settings/repair-orders/qz-wizard` | `tenant.admin.qz_wizard` | Printer setup wizard |

### Certificate & signing (deployment)

| Item | Value |
|------|-------|
| Config | `config/printing.php` → `printing.qz.*` |
| Env vars | `QZ_CERTIFICATE_PATH`, `QZ_PRIVATE_KEY_PATH`, `QZ_PRIVATE_KEY_PASSPHRASE`, `QZ_SIGNATURE_ALGORITHM` (default `sha512`) |
| Audited `.env` paths | Point to legacy `/home/master/applications/production/public_html/qz/` — **not found on server** |
| V1 doc | `docs/ARK-SMS-PRINTING-QZ-KEY-TAGS.md` § QZ signing |

---

## 3. Brother QL-800 configuration

| Item | Default / typical |
|------|-------------------|
| Printer name fallback | `Brother QL-800` (`config/printing.php`) |
| Label width | **62 mm** (DK-2205 continuous) |
| Label height | **38.1 mm** |
| DPI | Auto via `RasterDpiResolver` — often **300** on Mac-class UA, else config `key_tag.default_dpi` (**300** in config) |
| Orientation | `auto` — landscape when width ≥ height |
| Media | `mono` or `red_black` (`qz_key_tag_media_type`) |
| VIN on key tag | `qz_key_tag_vin_display`: `last6` (default), `last8`, or `full` |

Oil sticker inherits key-tag dimensions/printer when oil-specific settings empty.

---

## 4. Shop / printer settings (persistence)

ARK-SMS uses **tenant DB** `static_options` (not V2 `shop_settings`).

| Option key | Purpose |
|------------|---------|
| `qz_printing_key_tag_printer` | Key tag printer name |
| `qz_printing_oil_sticker_printer` | Oil sticker printer (empty → key tag printer) |
| `qz_printing_ro_printer` | RO letter PDF printer |
| `qz_printing_auto_key_tag_on_create` | Auto-print key tag on check-in (`1`/`0`) |
| `qz_key_tag_label_width_mm` / `height_mm` | Key tag dimensions |
| `qz_key_tag_media_type` | `mono` / `red_black` |
| `qz_key_tag_orientation` | `auto` / `portrait` / `landscape` |
| `qz_key_tag_vin_display` | `last6` / `last8` / `full` |
| `qz_raster_dpi` | `203` / `300` / empty = auto |
| `qz_ql_key_tag_scale_content` | QZ scaleContent toggle |
| `qz_oil_sticker_*` | Parallel oil sticker overrides |
| `oil_change_sticker_next_due_months` | Calendar due months override |

Per-location: same keys with **`:location_id`** suffix.

**Table:** `location_print_settings` — `location_id`, `qz_raster_dpi` (nullable).

**Authority class:** `app/Support/TenantPrintingSettings.php`

**V2 migration target:** Map into `shop_settings` (single-shop) with same semantic keys; preserve printer name indirection.

---

## 5. Print document routes

| Label | Method | URI | Controller |
|-------|--------|-----|------------|
| Key tag PDF | GET | `/app/repair-orders/{repairOrderNumber}/print-key-tag` | `KeyTagPrintController` |
| Oil sticker PDF | GET | `/app/repair-orders/{repairOrderNumber}/print-oil-change-sticker` | `OilChangeStickerPrintController` |

Route names: `tenant.admin.repair_order.print_key_tag`, `tenant.admin.repair_order.print_oil_change_sticker`.

Controllers return **inline PDF bytes** with print job headers (`X-Print-Job-Id`, `X-Print-Batch-Id`, `X-Print-Idempotency-Key`, `X-Print-Printer`, `X-Print-Source`).

---

## 6. Services & domain logic (port list)

### Rendering pipeline

| Class | Role |
|-------|------|
| `KeyTagPdfRenderer` | RO → PDF bytes |
| `OilChangeStickerPdfRenderer` | RO → PDF bytes |
| `KeyTagRenderNormalizer` / `OilChangeStickerRenderNormalizer` | Template normalization |
| `KeyTagTemplateService` / `OilChangeStickerTemplateService` | DB template blocks |
| `PrintRoutingService` | Document type → printer name |
| `RasterDpiResolver` | DPI for raster path |

### Print context (authority — port verbatim logic)

| Class | Role |
|-------|------|
| `KeyTagPrintContext` | Customer, vehicle, plate, VIN display, business name from location |
| `OilChangeStickerPrintContext` | Mileage, next due mi/date, shop line, oil type |
| `OilChangeStickerOilTypeResolver` | Oil type line inference |
| `KeyTagQrCodeGenerator` / `KeyTagRoBarcodeSvgGenerator` | QR/barcode on key tag |

### Key tag fields (from `KeyTagPrintContext`)

- Business name (location name, not app title)
- Customer name (18 char limit)
- Vehicle YMM (30 char)
- License plate line
- VIN: last6 / last8 / full per setting
- RO number / barcode / QR via template blocks

### Oil sticker fields (from `OilChangeStickerPrintContext`)

- Shop name (location)
- Vehicle + current mileage combined line
- Next oil mileage (`current + 5000` default)
- Due date (`+6 months` default from service anchor)
- `Due: {mi} or {date}` combined line
- Oil type line (inferred)
- Optional QR

---

## 7. Templates

### Blade PDF shells

- `resources/views/tenant/admin/repair_orders/pdf/key-tag.blade.php`
- `resources/views/tenant/admin/repair_orders/pdf/oil-change-sticker.blade.php`
- Block partials in `key_tag_blocks/`, `oil_change_sticker_blocks/`

### DB template models

- `app/Models/KeyTagTemplate.php`, `KeyTagTemplateBlock.php`
- `app/Models/OilChangeStickerTemplate.php`, `OilChangeStickerTemplateBlock.php`
- Enums: `KeyTagBlockType`, `OilChangeStickerBlockType`

### Layout admin (defer to Phase 2 in V2)

- `KeyTagLayoutController`, `OilChangeStickerLayoutController`
- Views: `settings/key_tag_layout/`, `settings/oil_change_sticker_layout/`

**Phase 1 V2:** Port default template rendering + hardcoded/seeded layout equivalent to v1 production output — not layout editor.

---

## 8. UI entry points (ARK-SMS)

| Action | Location | Client call |
|--------|----------|-------------|
| Print key tag | `repair_orders/edit.blade.php` | `arkPrintDocument(printer, url, btn, { document: 'key_tag', resolvePrinter: true })` |
| Auto key tag on check-in | Session `qz_auto_key_tag_ro_id` → same URL on DOM ready | `printSource: 'auto_checkin'` |
| Print oil sticker | Same RO edit surface (search `oil_change_sticker` / `arkPrintDocument`) | `document: 'oil_change_sticker'` |
| Printer setup | QZ Wizard route | Loads `qz-tray.js` immediately |
| Test prints | `PrintTestController`, `print_test/key-tag.blade.php` | `print_test_key_tag` document type |

**V2 target entry points:**

- RO review → Print key tag (one click)
- Vehicle / service → Print oil sticker (one click)

---

## 9. Print routing (document types)

`PrintRoutingService` maps client `options.document` → printer:

| Document type | Printer resolver |
|---------------|------------------|
| `key_tag`, `print_test_key_tag` | `TenantPrintingSettings::keyTagPrinter()` |
| `oil_change_sticker`, `print_test_oil_change_sticker` | `TenantPrintingSettings::oilStickerPrinter()` |
| `repair_order_pdf`, `print_test_ro_letter` | `TenantPrintingSettings::roPrinter()` |

---

## 10. Failure posture (ARK-SMS — preserve in V2)

- QZ JS load failure → `QZ_TRAY_JS_LOAD_FAILED`
- QZ not connected → user-facing errors in `print-helpers` (do not silent-fail)
- QL raster failure codes: `KEY_TAG_QL_RASTER_FAILED`, `KEY_TAG_MAC_QL_RASTER_FAILED`
- Signing failure → `qz-signing-failed` custom event

**V2 message (doctrine):**  
*"QZ Tray is not connected. Verify QZ Tray is running and the printer is online."*

---

## 11. ARK V2 migration map

| ARK-SMS artifact | ARK V2 target | Phase |
|------------------|---------------|-------|
| `TenantPrintingSettings` | `shop_settings` columns or JSON + `ShopSettings` | 1 |
| `QzTraySignController` + `QzTraySigning` | `app/Ark/Operations/Printing/` + `routes/operations.php` | 1 |
| `print-helpers.blade.php` (core) | `resources/js/ark-qz-print.js` + minimal Blade bootstrap | 1 |
| `ark-qz-key-tag.js` | Port as-is | 1 |
| `KeyTagPrintContext` + `KeyTagPdfRenderer` | `app/Ark/Operations/Printing/KeyTag/` | 1 |
| `OilChangeStickerPrintContext` + renderer | `app/Ark/Operations/Printing/OilSticker/` | 1 |
| `vehicle_maintenance_intervals` oil interval | Shop setting or config default 5000 | 1 |
| DB template editors | Defer — use v1 default layout | 2 |
| `location_print_settings` | N/A single-shop — `shop_settings` only | 1 |
| `docs/ARK-SMS-PRINTING-QZ-KEY-TAGS.md` | Copy/reference in `docs/printing/` | 0 ✓ |

---

## 12. Parity verification checklist

On advisor workstation with QZ Tray + Brother QL-800:

- [ ] Key tag from ARK-SMS (baseline)
- [ ] Key tag from ARK V2 matches baseline
- [ ] Oil sticker from ARK-SMS (baseline)
- [ ] Oil sticker from ARK V2 matches baseline
- [ ] One-click from RO review (no wizard)
- [ ] Printer name from settings, not hardcoded
- [ ] Mileage / due date match server-computed values
- [ ] QZ disconnected shows clear error

---

## 13. Open questions

1. **QZ cert paths** on production `.env` reference missing files — confirm whether signing works unsigned, or certs live elsewhere.
2. **Tenant `static_options` values** for Auto Repair Keeper shop (printer name, mm overrides) — sample from tenant DB when connection available.
3. **V2 template strategy:** Seed v1 default blocks vs inline Blade-only for Phase 1?

---

## Sign-off

| Field | Value |
|-------|-------|
| Status | `AUDITED` |
| Source | `root@24.144.81.19:/var/www/sites/ark-sms/production/current` |
| V1 git release | `20260531023539` |
| All six blocking questions answered | **Yes** |
| Ready for V2 port implementation | **Yes** (parity port, not redesign) |
