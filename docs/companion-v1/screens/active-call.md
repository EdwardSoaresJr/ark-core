# Screen spec — Active Call

**ID:** `companion.screen.active-call`  
**Role(s):** Advisor  
**Quo ref:** `references/external/quo/` screensdesign in-call frames — see [`references/external/quo.md`](../references/external/quo.md)  
**Status:** 📝 draft — Edward review

---

## Job

Stay on the call with **shop actions one tap away** — never hunt the desktop for RO, text, or payment.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Strong in-call grid (Calendars · Notes · Tasks · Payments · Profile) | **Target: Yes** |
| **Why** | CRM contact tools | Same grid rhythm — **Open RO · Text · Schedule · Pay · Note** on **vehicle/customer** context |

---


**Top bar**

- Minimal back (disabled or ends call with confirm)
- **DND** — defer P1

**Center (compact while connected)**

- Customer name · phone
- Timer / call state (Ringing → Connected)

**Bottom card (fixed)**

  1. **Open RO**
  2. **Text**
  3. **Schedule**
  4. **Pay**
  5. **Note**
- **Telephony row:** Earpiece · Mute · Hold · Keypad · **End** (red, largest)

Optional expand: swipe up on card for estimate one-liner + last message.

---

## Interaction patterns

| Pattern | Behavior |
|---------|----------|
| Tool grid tap | Opens **sheet or inline panel** — call stays connected |
| End | → [`post-call.md`](post-call.md) |

---

## Flows

**From:** [`incoming-call.md`](incoming-call.md) Answer · outbound from search/thread/dialer

**Exit:** End → post-call · never dump to Home first

---

## Data & API

In-call context = same payload as incoming + live timer  
Sheets may need RO id · balance · appointment slots — `/api/mobile` as needed

---

## Edward sign-off

- [ ] Ready for Flutter
