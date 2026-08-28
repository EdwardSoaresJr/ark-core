# Screen spec — Schedule Day

**ID:** `companion.screen.schedule-day`  
**Role(s):** Advisor  
**Status:** 📝 draft — Edward review

---

## Job

See **today's appointments and arrivals** — tap customer · vehicle · RO · check in without desktop schedule board.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Generic calendar · CRM appointments | **Target: Yes** |
| **Why** | Contact-centric events | **Vehicle · RO link · shop arrival workflow** on every row |

---

## Layout

### Shell

- **Title:** Today · date subtitle · chevron → day picker (week strip P1)
- **Filter chips:** All · Arriving · In shop · Waiting pickup

### Timeline list

Grouped by time block or continuous sorted by start:

**Row (~72pt):**

- Time — `9:00 AM`
- Customer · vehicle — `John Smith · 2020 F-150`
- Service summary — one line
- Status chip — `Arriving` · `Checked in` · `RO #1602`
- Chevron

### Empty

- "No appointments today" · tap to book — P1

### Tab bar

- Schedule tab active

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap row | Appointment detail sheet |
| Swipe right | Call customer |
| Swipe left | Text customer |
| Pull to refresh | Reload day |

---

## Appointment detail (sheet)

- Customer · vehicle · phone
- Notes · requested services
- Linked RO or **Check in → create/link RO**
- Actions: Call · Text · Open RO · Reschedule (P1)

---

## States

| State | UX |
|-------|-----|
| Loading | Skeleton |
| Past appointments | Muted section at bottom optional |

---

## Flows

Schedule tab → row → check in → RO workspace

Search → Schedule action → same sheet

Active call → Schedule tool → this day view with customer pre-filtered

---

## Data & API

**Needs:** appointments for day + arrival flags + RO ids  
**May need:** `/api/mobile/schedule?date=` · check-in action endpoint

---

## Edward sign-off

- [ ] Useful for front counter morning scan
- [ ] Ready for Flutter
