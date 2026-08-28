# ARK-SMS → ARK V2 Label Printing Migration

> **Doctrine:** `docs/printing/ark-printing-doctrine.md` — printing is operational infrastructure (like invoices and estimate PDFs), not a convenience feature.

## Context

This is **not** a greenfield label-printing project.

ARK-SMS already contains working:

- QZ Tray integration
- Brother QL-800 printing
- Label templates
- Shop settings
- Operational workflows for key tags and oil change stickers

The goal is to **migrate proven functionality** into ARK V2 while preserving V2 authority boundaries and workflow structure.

**Do not redesign printing until migration parity is achieved.**

---

## Phase 1 goal

Restore operational printing in ARK V2:

| Sticker | Typical fields |
|---------|----------------|
| **Key tag** | RO number, customer, year/make/model, plate, advisor, date, barcode/QR (if v1 supports) |
| **Oil change reminder** | Customer, vehicle, current mileage, next service mileage, service date, shop name, shop phone |

**Stack:** Browser → QZ Tray → Brother QL-800

**Source of truth for implementation:** ARK-SMS v1 codebase and its database settings — not this document alone.

---

## Required first step

**Audit ARK-SMS before writing code.**

Document findings in:

`docs/printing/ark-sms-printing-audit.md` — **status `AUDITED` (2026-06-06)** from ReleasePanel server `root@24.144.81.19`, path `/var/www/sites/ark-sms/production/current`.

V1 canonical detail remains on server: `docs/ARK-SMS-PRINTING-QZ-KEY-TAGS.md`.

### Where to audit

ARK-SMS v1 is not in this repository. Audit the **legacy ARK-SMS application** (production deploy, v1 git repo, or local `arksms` database).

Useful commands when legacy DB is available:

```bash
php artisan ark:import-legacy-arksms --audit-schema
```

Search v1 codebase for: `qz`, `QZ`, `brother`, `ql-800`, `label`, `sticker`, `key tag`, `oil`.

---

## Migration scope

### Key tag labels

Support the same workflow as ARK-SMS:

- RO number
- Customer name
- Vehicle year / make / model
- License plate
- Advisor
- Date
- Barcode / QR (if already supported in v1)

### Oil change stickers

Support the same workflow as ARK-SMS:

- Customer name
- Vehicle
- Current mileage
- Next service mileage
- Service date
- Shop phone number
- Shop name

### Reuse existing logic

If ARK-SMS already calculates next oil service mileage, sticker formatting, or label dimensions — **port it**. Do not rewrite working business logic.

---

## Printer settings

Migrate existing settings authority.

If ARK-SMS stores printer name, label size, print preferences — map into ARK V2 `shop_settings` (or the documented V2 settings surface). **Do not hardcode printer names.**

---

## QZ Tray

Preserve: **Browser → QZ Tray → Brother QL-800**

Port:

- QZ JS integration
- Certificate / signing configuration
- Printer discovery / selection flow (as implemented in v1)

Do not introduce cloud printing or PDF-only workarounds unless v1 requires them.

---

## Authority boundaries (ARK V2)

Printing consumes authoritative server data only.

| Label | Authority |
|-------|-----------|
| Key tag | Repair order, customer, vehicle, advisor |
| Oil sticker | Vehicle, mileage, service interval, shop settings |

Financial and mileage calculations stay server-side. JavaScript triggers print with server-prepared payload only.

---

## Visual direction

- **Key tag:** fast, readable, operational
- **Oil sticker:** clean, professional, service-reminder

Match v1 output first. No label redesign during migration.

---

## Migration order

1. Audit ARK-SMS printing implementation → `ark-sms-printing-audit.md`
2. Inventory templates, settings, routes
3. Port printer settings
4. Port QZ integration
5. Port key tag printing
6. Port oil sticker printing
7. Validate Brother QL-800 output side-by-side with v1
8. Add ARK V2 operational entry points

---

## Suggested V2 entry points

| Action | Surface |
|--------|---------|
| Print key tag | RO review / workspace header actions |
| Print oil reminder | Vehicle card / post-service / RO context |

One-click operational access — same speed as ARK-SMS.

---

## Verification

Compare ARK V2 output against ARK-SMS output on the same Brother QL-800.

**Success criteria:**

- Same printer
- Same workflow
- Same operational speed
- Same label quality

ARK V2 should not feel “different” on day one. Operationally equivalent first.

---

## Priority

Label printing is high-frequency daily work for advisors and techs. Without key tags and oil stickers, staff bounce back to ARK-SMS and V2 adoption stalls.

**Treat this migration ahead of many other features.**
