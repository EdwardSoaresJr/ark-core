# Screen spec — Customer Workspace

**ID:** `companion.screen.customer-workspace`  
**Role(s):** Advisor  
**Status:** 📝 draft — Edward review

---

## Job

**Relationship home on mobile** — who they are · what vehicles · what work is open · one tap to call · text · pay · schedule.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Call · Message · Email row · CRM fields | **Target: Yes** |
| **Why** | Contact-centric | **Customer + vehicles + open ROs** first · timeline is shop events not marketing tags |

---

## Layout

### Header — identity

- Customer name — Display
- Phone · email — tap to call / mailto
- Billing class / referral — muted labels (if set)


Horizontal equal buttons:

- **Call** · **Text** · **Schedule** · **More** (Pay · New RO · Edit)

### Section — Vehicles

Card list — YMM · plate · mileage if known · tap → vehicle workspace

### Section — Open repair orders

Each row: `#1599` · vehicle · status chip · total · tap → RO workspace

### Section — Recent activity (timeline)

Newest first — messages · calls · estimate viewed · approval · payment  
Tap row → thread · call detail · RO

### Footer

- **New repair order** — secondary full-width

No tab bar when pushed from search/RO — back returns caller.

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Pull to refresh | Reload projection |
| Tap Text | Thread |
| Tap vehicle | Vehicle workspace |
| Long press phone | Copy |

---

## States

| State | UX |
|-------|-----|
| No open ROs | Section empty · "New repair order" prominent |
| Multiple vehicles | All listed · primary flagged |

---

## Flows

**Entry:** Search → History · thread header tap · RO workspace customer chip

**Exit:** Back to caller — never force Home

---

## Data & API

**Needs:** customer hub projection — vehicles · open ROs · timeline slice  
**Existing:** Customer Hub desktop — `/api/mobile/customers/{id}` parity

---

## Edward sign-off

- [ ] Ready for Flutter
