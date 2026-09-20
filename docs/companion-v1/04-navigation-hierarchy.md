# Deliverable 4 — Navigation Hierarchy

**Advisor v1** — top level first, then everything underneath. Every leaf links to a spec in [`screens/`](screens/).

```text
Companion (Advisor)
│
├── Home                                    → home-continuity.md
│   ├── Continuity moment detail            → morning-continuity-detail.md
│   ├── Notifications inbox (bell)          → notifications-inbox.md
│   └── (deep links to workspaces)
│
├── Communications                          → conversation-list.md
│   ├── Conversation thread                   → conversation-thread.md
│   ├── Compose / reply sheet                 → compose-reply-sheet.md
│   ├── Internal note                         → internal-note.md
│   ├── Calls library (segment or More)       → calls-library.md
│   ├── Incoming call (overlay)               → incoming-call.md
│   ├── Active call (overlay)                 → active-call.md
│   ├── Post-call (sheet)                     → post-call.md
│   └── Outgoing / dialer                     → outgoing-call.md
│
├── Search                                  → global-search.md
│   └── Results → action sheet → workspace
│
├── Schedule                                → schedule-day.md
│   ├── Appointment detail (sheet)            → appointment-detail.md
│   └── Check-in                              → check-in-arrival.md
│
├── More
│   ├── Quick intake (P1)                     → quick-intake.md
│   ├── Profile / Settings                    → settings-profile.md
│   └── About
│
└── Workspaces (stack — not tabs)
    ├── Customer                              → customer-workspace.md
    ├── Vehicle                               → vehicle-workspace.md
    ├── Repair order                          → repair-order-workspace.md
    │   ├── Concern detail                      → concern-detail.md
    │   ├── Timeline / notes                    → ro-timeline-notes.md
    │   ├── Payment sheet                       → payment-sheet.md
    │   └── Estimate send / approval            → estimate-send-approval.md
    ├── Inspection overview                   → inspection-overview.md
    │   └── Inspection item                     → inspection-item.md
    └── Photo viewer                          → photo-viewer.md
```

---

## Technician v1 hierarchy (separate root)

```text
Companion (Technician)
│
├── My Work                                 → my-work.md
├── RO workspace (assigned)                 → repair-order-workspace.md (tech mode)
│   ├── Inspection overview                 → inspection-overview.md
│   ├── Inspection item + camera            → inspection-item.md
│   └── Internal note                       → internal-note.md
├── Bay orientation (P1)                    → bay-orientation.md
└── More → settings-profile.md
```

**Never in tech root:** Communications tab · Global search · Schedule · Payments

---

## Owner mode (P1 — not advisor tabs)

```text
Owner entry (More or separate login intent)
├── Owner pulse                             → owner-pulse.md
└── Shop feed                               → shop-feed-owner.md
```

---

## Workspace shell rule

**One shell** owns:

- Persistent identity strip (customer · vehicle · RO)
- Single back stack
- Context action bar (changes per workspace — not duplicated)

**Sub-views are bodies**, not nested apps with their own nav bars.

Reference legacy failure: `docs/mobile/ark-mobile-ux-audit-v1.md` SYS-1.

---

## Overlay rules

| Surface | Nav behavior |
|---------|--------------|
| Incoming / active call | No tab bar · full bleed |
| Post-call · payment · compose | Sheet over caller workspace |
| Photo viewer | Modal · swipe dismiss |
| Login | Blocks tabs until complete |

---

## Depth budget

| Depth | OK? | Example |
|-------|-----|---------|
| 1 tap from tab | ✅ | Comms → thread |
| 2 taps | ✅ | Home → continuity → thread |
| 3 taps | ⚠️ | Max for P0 — flag in flows |
| 4+ | ❌ | Redesign |

---

## Back stack contract

| From | Back goes to |
|------|--------------|
| Thread | List (scroll restored) |
| RO workspace | Caller (search · thread · home row) — **not** tab root unless caller was tab |
| Inspection item | Overview or RO |
| Payment success | RO workspace or dismiss to caller |
| Search action | Previous workspace or search |

**Anti-pattern:** back from RO → Home when user came from thread.

---

## Sign-off

- [ ] Edward: can name any screen's parent in one breath
- [ ] No orphan screens outside hierarchy
- [ ] Spec links above match [`01-screen-inventory.md`](01-screen-inventory.md)
