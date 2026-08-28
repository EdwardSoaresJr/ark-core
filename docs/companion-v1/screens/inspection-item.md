# Screen spec — Inspection Item

**ID:** `companion.screen.inspection-item`  
**Role(s):** Technician (capture) · Advisor (review)  
**Status:** 📝 draft — Edward review

---

## Job

**One inspection line item** — see finding · photos · video · severity · add advisor response — the destination for **"Ben uploaded inspection"** push.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | No inspection authority | **Target: Yes** |
| **Why** | N/A | Native **finding + evidence + RO concern** — advisor reviews without desktop |

---

## Layout

### Header

- **Back** → inspection overview or RO workspace (preserve stack)
- Title — item name · `Brake pads — front`
- RO chip · `#1599` · vehicle subtitle
- Progress — `Item 4 of 12` (tech) · **Needs review** badge (advisor push)

### Body — finding block

- **Status** — Pass · Monitor · Fail · Not inspected
- **Finding text** — tech narrative · editable (tech only)
- **Measurements** — if any · read-only for advisor
- **Recommendation** — linked ops preview · deferred/approved chips

### Media strip

- Horizontal thumbnails · tap → [`photo-viewer.md`](photo-viewer.md)
- **Add photo / video** — tech only · camera entry
- Upload progress inline

### Advisor review zone (visible when push = review)

- **Internal note field** — "Ask Ben to get closer shot…"
- **Send to customer** — P1 · inspection link
- **Add to estimate** — jump to concern · not full estimate builder P0

### Tech capture zone (tech role)

- **Camera** · **Video** · **Mark complete**
- Large capture buttons — glove-friendly

### Footer actions (role-aware)

| Role | Actions |
|------|---------|
| Tech | Save · Mark item complete · Next item |
| Advisor | Reply note · Open RO · Mark reviewed |

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap photo | Full-screen viewer · swipe between |
| Pinch | Zoom in viewer |
| Swipe item nav | Previous / next inspection item (tech) |
| Pull to refresh | Reload finding + media |
| Long press photo | Share · save (policy) |

---

## States

| State | UX |
|-------|-----|
| Push: photo uploaded | Open **this item** · photo highlighted |
| No media yet | Empty state · camera CTA (tech) |
| Upload failed | Retry on thumbnail |
| Advisor read-only | Hide capture · show review actions |

---

## Flows

**Push "Ben uploaded inspection" →** this screen (deep link with `ro_id` + `item_id`)

Review photo → internal note → send → back to RO workspace

**Tech:** My Work → RO → inspection overview → item → capture → next

Link: [`../02-flows.md`](../02-flows.md#notification--inspection-ben-uploaded)

---

## Data & API

**Existing:** inspection item authority · photo stream `/api/mobile/repair-orders/{ro}/inspection-photos/{photo}`

**Needs:**

- Item projection with finding · media ids · concern link
- Push deep link route schema
- Internal note POST on RO/production note path

---

## Edward sign-off

- [ ] Push lands on exact item — never Home hunt
- [ ] Advisor can respond in one screen
- [ ] Ready for Flutter
