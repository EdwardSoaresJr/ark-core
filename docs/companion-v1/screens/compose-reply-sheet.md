# Screen spec — Compose / Reply Sheet

**ID:** `companion.screen.compose-reply`  
**Role(s):** Advisor  
**Quo ref:** `quo-threads.png` — minimal composer rhythm  
**Status:** 📝 draft — Edward review

---

## Job

Send SMS (and MMS) **from anywhere** — reply in thread · new message to customer · post-call text — one composer, same API as desktop Quick Reply.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Solid composer · attachments | **Target: Yes** |
| **Why** | Generic | Same UX **plus** shop quick inserts · estimate/pay/inspection links · customer picker with vehicle line |

---

## Layout

### Presentation

- **Inline** — bottom of [`conversation-thread.md`](conversation-thread.md) (default)
- **Sheet** — half-screen when opened from post-call · FAB · RO workspace
- **Full-screen compose** — new message: pick customer first, then same composer

### Composer bar

- Attachment (camera · gallery · file)
- Multiline text field — grows to 4 lines max then scrolls
- Send button — primary when content or attachment present
- Character count — hidden unless approaching SMS segment limit

### Quick insert row (above field, horizontal scroll)

- **Templates** — shop-configured snippets ("We're running 15 min late")
- **Insert link** — Estimate · Payment · Inspection (same as Quick Reply rail)
- Inserts append to field · server builds portal URL

### New message — customer picker (step 1)

- Search field — reuse search projection
- Rows: customer · vehicle · last message time
- Select → thread opens or creates · composer focused

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Send | Optimistic UI · authoritative server |
| Attachment | Native picker · compress images |
| Tap template | Insert at cursor |
| Swipe down (sheet) | Dismiss · draft preserved per thread |
| Keyboard | Composer sticks above keyboard |

---

## States

| State | UX |
|-------|-----|
| Reply | Thread context in strip above composer |
| New | Customer picker then composer |
| Sending | Disable send · spinner |
| Failed | Toast + retry |
| MMS uploading | Progress on bubble |

---

## Flows

- Thread → type → send → stay in thread
- Post-call → Send text → sheet with customer pre-filled
- FAB on list → picker → compose → thread
- RO workspace → Message customer → sheet with RO context

---

## Data & API

**Existing:** `SendOutboundMessageAction` · link actions — expose via `/api/mobile/conversations/...`  
**Same authority:** `ConversationMessage` only — no parallel SMS store

---

## Edward sign-off

- [ ] Ready for Flutter
