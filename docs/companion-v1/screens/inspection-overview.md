# Screen spec — Inspection Overview

**ID:** `companion.screen.inspection-overview`  
**Role(s):** Technician · Advisor  
**Status:** 📝 draft — Edward review

---

## Job

See **all inspection items on an RO** — progress · what failed · what's missing media · jump to item.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | No | **Target: Yes** |

---

## Layout

### Header

- Vehicle · RO # · inspection template name
- Progress bar — `8/12 complete` · `% failed`

### List sections

Group by concern or template section:

**Row (~56pt):**

- Item name
- Status icon — pass · fail · monitor · empty
- Media count badge · photo icon
- Chevron

Failed / needs review — sorted to top for advisor

### Footer (tech)

- **Continue inspection** → first incomplete item
- **Submit inspection** — when all required items done · confirm sheet

---

## Flows

RO workspace → Inspection → this list → item

Advisor → same list · read-only capture · tap failed row → item review

---

## Data & API

**Needs:** `GET /api/mobile/repair-orders/{id}/inspection` — items summary array

---

## Edward sign-off

- [ ] Scannable progress for advisor standing at counter
- [ ] Ready for Flutter
