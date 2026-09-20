# Screen spec — Conversation Thread

**ID:** `companion.screen.conversation-thread`  
**Role(s):** Advisor  
**Quo ref:** `references/external/quo/quo-threads.png` · `screensdesign-5.webp` (thread rhythm)  
**Status:** 📝 draft — Edward review

---

## Job

Read and reply in **one thread** with customer · vehicle · RO visible — send estimate · pay · schedule without leaving the conversation.

---

## Product quality gate


| | Reference CRM | Quo | ARK Companion |
|---|-----|-----|---------------|
| **Verdict** | Unified timeline · CRM Manage sheet | Clean thread · minimal header | **Target: Yes** |
| **Why** | Create Opportunity · Review request | No vehicle · RO · estimate | Identity strip + **shop Manage sheet** + quick actions on every message |

---

## Layout (production spec)

### Shell — identity strip (persistent, collapses on scroll)

- **Back** → conversation list (preserves scroll position)
- **Center stack:**
  - Customer name — Display
  - Vehicle line — `2019 Honda Civic · ABC123`
  - RO chip — `#1599 · Waiting approval`
- **Header actions (right):** Call · **More (⋯)** → Manage sheet

Strip **never** shows bare phone number when customer is known.

### Body — message timeline


- Date separators — `Today` · `Yesterday` · `Jun 12`
- **Message bubbles** — inbound left · outbound right
- **Channel label** on mixed threads — SMS · Email · Call (icon + one word, muted)
- **Call rows** — duration · inbound/outbound · tap → call detail / playback (P1)
- **Voice memo / MMS** — inline thumbnail · tap full-screen
- **System rows** — estimate sent · viewed · approved (muted center line — not fake chat)

**Scroll:** newest at bottom · composer pinned · keyboard pushes composer up

### Context rail (optional P0 — collapsed chip row above composer)

Horizontal chips — tap expands sheet, does not navigate away:

- **Open RO** · **Estimate** · **Pay** · **Schedule** · **Inspection**

If rail omitted in v1, Manage sheet carries all actions.


- **Attachment** — photo · file (MMS)
- **Text field** — placeholder `Message Emma…`
- **Send** — enabled when non-empty · haptic on send
- **Quick actions row** (above composer, collapsible): Send estimate · Payment link · Inspection link — same as desktop Quick Reply rail

No tab bar on thread (full-bleed workspace).

---


**Trigger:** ⋯ or long-press header

|-------------------------|----------------------|
| Create Opportunity | **Open RO** |
| Review request | **Send estimate** |
| Add to campaign | **Take payment** |
| Generic tags | **Schedule / Book appointment** |
| — | **Mark handled** · **Internal note** |

Sheet: half-height · draggable · dismiss swipe down.

---

## Typography & density

| Element | Style | Example |
|---------|-------|---------|
| Customer name | Display | Emma Hathorn |
| Vehicle · RO | Body | 2019 Civic · RO #1599 |
| Message body | Body | Can I pick up at 5? |
| Timestamp | Label | 2:14 PM |
| Channel | Label muted | SMS |

Bubble max width ~75% · line height comfortable for thumb scroll.

---

## Components

- Identity strip (thread variant)
- Message bubble (in/out/system)
- Channel badge
- Composer bar
- Manage bottom sheet
- Quick action chips

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap bubble | Copy text · long-press copy |
| Long press message | Copy · resend template (outbound only) |
| Swipe left on row | Reply shortcut (focus composer + quote) — optional P1 |
| Tap Call (header) | Outgoing call → [`active-call.md`](active-call.md) |
| Tap attachment | Full-screen viewer · share sheet |
| Pull down from top | Refresh thread |
| Tap RO chip | Open [`repair-order-workspace.md`](repair-order-workspace.md) |
| Tap customer name | Open [`customer-workspace.md`](customer-workspace.md) |
| Send | Optimistic bubble · server authoritative · error retry inline |

---

## States

| State | UX |
|-------|-----|
| Default | Timeline + composer |
| Loading | Skeleton bubbles · identity strip from list cache |
| Empty thread | "Start the conversation" · composer focused |
| Customer turn | Subtle banner `Waiting on you` — optional |
| Send failed | Red exclamation on bubble · tap retry |
| Offline | Composer disabled · banner · read cache |
| Internal note mode | Composer tint · staff-only badge — from Manage |

---

## Flows

**Entry:**

- [`conversation-list.md`](conversation-list.md) tap row
- Push "Emma replied" → this screen (composer optional auto-focus)
- [`global-search.md`](global-search.md) → Text
- [`post-call.md`](post-call.md) → Send text

**Exit:**

- Back → list (scroll restored)
- Open RO → RO workspace · back returns **thread**, not Home
- Send → stay in thread · pocket OK

Link: [`../02-flows.md`](../02-flows.md#notification--customer-replied)

---

## Data & API

**Projection needs:**

- Thread messages (paginated, cursor)
- Customer + primary vehicle + open RO summary
- Estimate posture · balance due
- Turn indicator (customer vs shop)
- Quick reply templates / link actions

**Existing:** conversation + Quick Reply patterns on desktop — mirror for mobile  
**May need:** `GET /api/mobile/conversations/{id}` · `POST .../messages` · link actions bundle

---



**Quo gets right:** calm density · readable previews · shared inbox clarity

**ARK adds:** vehicle + RO always in strip · shop Manage actions · estimate/pay/inspection quick row · system events from authority · **no CRM opportunity noise**

---

## Edward sign-off

- [ ] Ready for Flutter
