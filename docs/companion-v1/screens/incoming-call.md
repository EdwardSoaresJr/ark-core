# Screen spec — Incoming Call

**ID:** `companion.screen.incoming-call`  
**Role(s):** Advisor  
**Quo ref (external):** `references/external/quo/incoming-phone-menu-insight.png` · `caller-id-marketing.webp`  
**Status:** 📝 draft — Edward review

---

## Job

Answer customer calls with **full shop context before picking up** — never hunt after hangup.

---

## Product quality gate


| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Contact name + generic fields | **Target: Yes** |
| **Why** | CRM contact card | Customer + **vehicle** + **open RO** + **estimate status** + **last message** + **advisor notes** on one screen before answer |

---

## Layout (production spec)

### Full-screen incoming (over lock screen when permitted)

**Top third — identity (largest)**

- Customer name — Display, bold
- Phone number — Label, if not already known customer
- Vehicle line — Title: `2019 Honda Civic · ABC123`
- RO badge — `#1599 · Waiting approval` status chip

**Middle — context cards (scroll if needed, default visible without scroll on Razr)**

1. **Estimate** — `Sent · viewed 2× · $1,847` or `No open estimate`
2. **Last message** — one line inbound preview + time · channel icon SMS
3. **Advisor notes** — max 2 lines internal · "Prefers text after 5pm"

**Bottom fixed — call actions**

- **Decline** — secondary, left
- **Answer** — primary, full width or prominent green · largest tap target
- **Message** — tertiary · send "Can't talk — text me" quick template (optional P1)

No tab bar. No hamburger. No hunt.

---

## Typography & density

Tight but readable at arm's length. Identity dominates. Metadata muted. One screen — no tabs inside incoming.

---

## Components

- Identity strip (compact variant)
- Status chip (RO lifecycle)
- Estimate summary card
- Message preview row
- Primary / secondary buttons

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap Answer | Native call connect · transition to Active Call screen |
| Tap Decline | Decline · optional post-decline sheet (text · voicemail) |
| Swipe up | Expand notes / timeline preview (optional) |
| No pull-to-refresh | Live data pushed until answered |

---

## States

| State | UX |
|-------|-----|
| Known customer | Full layout above |
| Unknown caller | Name = number · "New caller" · create customer after call |
| Multiple open ROs | RO badge shows primary + "2 open" · tap expands sheet |
| No network | Answer still works (PSTN/SIP) · context from cache + stale badge |

---

## Flows

**Entry:** OS incoming call → this screen (Companion is default call UI when registered)

**Answer →** [`active-call.md`](active-call.md) (minimal strip)

**Decline →** Post-call sheet or pocket

**Hang up from active →** [`post-call.md`](post-call.md) — note · text · schedule · open RO **without backing out to Home**

---

## Data & API

**Needs:**

- Customer resolve from caller ID
- Primary vehicle + open RO(s)
- Estimate posture (sent/viewed/approved/total)
- Last conversation message snippet
- Internal notes snippet (RO or customer)

**Existing:** caller lookup / customer hub projections (verify parity for mobile)  
**May need:** `GET /api/mobile/incoming-call/context?phone=` — **backend not frozen**

---

## Edward sign-off

- [ ] Ready for Flutter
