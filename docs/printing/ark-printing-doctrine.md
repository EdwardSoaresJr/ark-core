# ARK Printing Doctrine

Printing is **operational infrastructure**.

Key tag printing and oil change sticker printing are not optional convenience tools, UI flourishes, or document features. They sit alongside:

- Invoices
- Payments
- Estimate PDFs

Advisors and techs use labels dozens of times per day. If V2 cannot print key tags and oil stickers, staff return to ARK-SMS and adoption of everything else slows.

---

## Migration posture

This is **not** greenfield. ARK-SMS already prints perfectly on QZ Tray + Brother QL-800.

ARK V2's job: **achieve parity** — not invent a better printing system.

Same discipline as Financial Authority and Labor Authority: audit proven behavior, port authority boundaries, verify parity, then refine.

**No QZ code in V2 until** `docs/printing/ark-sms-printing-audit.md` status is `AUDITED` (completed 2026-06-06 from production server).

Printing is **not** a convenience feature, UI feature, or document feature — it is **operational infrastructure** in the same class as invoices, payments, and estimate PDFs.

---

## Fast path

Advisor must print in **one click** from the operational surface:

| Action | Where |
|--------|-------|
| Key tag | Repair order review |
| Oil sticker | Vehicle / service history |

**No** wizard. **No** settings-screen detour. **No** modal chain.

Settings configure the printer; they are not part of the print path.

---

## Authority source

Print payloads are built **server-side** from authoritative operational data.

### Key tag

| Authority | Data |
|-----------|------|
| Repair order | RO number, dates, advisor |
| Customer | Name |
| Vehicle | Year, make, model, plate |

### Oil sticker

| Authority | Data |
|-----------|------|
| Vehicle | Identity |
| Mileage | Current / in-out (authoritative RO or vehicle record) |
| Service data | Next service mileage, interval, service date |
| Shop settings | Shop name, phone |

**Never** calculate sticker values in JavaScript.  
**Never** duplicate mileage logic in the browser.

---

## Printer independence

- **Production target today:** Brother QL-800 via QZ Tray.
- **Architecture:** support future printers through **settings** (printer name, label size, media).
- **Business logic:** no Brother-specific assumptions in domain code — only in template/render layer if v1 does the same.

Do not hardcode printer names in code paths.

---

## Operational entry points

| Surface | Action |
|---------|--------|
| Repair order review | Print key tag |
| Vehicle / service history | Print oil sticker |

**Future (not Phase 1):**

| Surface | Action |
|---------|--------|
| Ready pickup | Reprint key tag |

Entry points live on operations surfaces (`/app/*`), not buried in admin or settings.

---

## Failure posture

If QZ Tray is unavailable, show an **operationally useful** error. Example:

> QZ Tray is not connected. Verify QZ Tray is running and the printer is online.

- Do **not** silently fail.
- Do **not** pretend print succeeded.
- Do **not** fall back to PDF print without an explicit audited v1 pattern.

---

## Parity first

Before any redesign:

| Dimension | Requirement |
|-----------|-------------|
| Workflow | Same as ARK-SMS |
| Speed | One-click from RO / vehicle |
| Information | Same fields, same layout intent |
| Print quality | Same Brother QL-800 output |

Redesign discussions **only after** side-by-side parity verification.

---

## Future boundary

| May evolve later | May not |
|------------------|---------|
| Label templates, visual layout | Printing authority (server-built payloads) |
| Additional printers via settings | Single authoritative mileage / service path |
| New entry points (e.g. reprint at pickup) | QZ → local printer workflow (until deliberately replaced) |

Printer output must always be generated from authoritative operational data.

---

## What implementers need next

Not more doctrine. **ARK-SMS source code** and a completed audit.

The audit must answer:

1. Where are the current templates?
2. How are dimensions defined?
3. How are printer names stored?
4. How are oil change mileages calculated?
5. How are QZ certificates handled?
6. What settings already exist?

Until those are documented, **do not write a single line of QZ code in ARK V2**.
