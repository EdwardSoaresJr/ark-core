# Screen spec — Repair Order Workspace

**ID:** `companion.screen.repair-order-workspace`  
**Role(s):** Advisor · Tech (read-heavy)  
**Status:** 📝 draft — Edward review

---

## Job

**RO command center in pocket** — status · customer · vehicle · estimate total · approval · comms · next action without opening desktop.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | No RO concept | **Target: Yes — category win** |
| **Why** | Pipeline/opportunity metaphor | Native **repair order** — the object advisors actually run |

---

## Layout

### Header — vehicle first (ARK hierarchy)

- **2019 Honda Civic** — Display
- Customer name — Body · tap → customer workspace
- **RO #1599** · lifecycle chip — `Waiting approval`
- **Estimate total** — authoritative · `$1,847.00`

### Status strip

- Advisor · tech assigned · promised time · keys on file — compact row

### Primary actions (2×2 or horizontal scroll)

- **Message** · **Call** · **Send estimate** · **Take payment** → [`payment-sheet.md`](payment-sheet.md)
- Secondary: **Schedule** · **Approve** · **Inspection**

### Sections (scroll)

1. **Concerns** — concern title · line count · status · tap → concern detail
2. **Approval / disposition** — approved · deferred · waiting — chips from authority
3. **Communications** — last message preview · tap → thread scoped to RO
4. **Timeline** — recent RO events (compact)
5. **Notes** — internal · tap → notes screen

### Tech role

Same screen · hide pay/estimate send if policy · emphasize concerns + inspection

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap concern | Concern detail |
| Tap Message | Thread with RO context |
| Swipe section | — |
| Pull to refresh | Reload RO projection |

---

## States

| State | UX |
|-------|-----|
| Draft / estimate | Emphasize send estimate |
| Waiting approval | Banner + customer decision pressure copy |
| Approved · in production | Tech + parts signals |
| Closed | Read-only · receipt link |

---

## Flows

**Entry:** Search · thread Manage · active call grid · continuity row · notification

**Exit:** Back preserves stack (thread · search · home)

Link: [`../02-flows.md`](../02-flows.md)

---

## Data & API

**Needs:** RO show projection — concerns summary · totals via `EstimateTotalsCalculator` · comms snippet · lifecycle  
**Existing:** RO show on desktop — `/api/mobile/repair-orders/{id}` mobile projection

---

## Edward sign-off

- [ ] This is why Companion exists — beats any CRM on RO context
- [ ] Ready for Flutter
