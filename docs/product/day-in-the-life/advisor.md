# Advisor — Edward

**Station:** Front Counter (fixed) + Portable Station (phone)  
**Primary question:** Who needs a response or decision?

Edward is a service advisor at Demo Auto Repair. He splits time between the Front Counter VVX and the lot. ARK must treat his phone as a **Portable Station peer** — not a shrunken inbox app.

---

## 7:55 AM — Unlock Portable Station

**Floor:** Shop opens at 8. One vehicle left overnight. Two appointments on the board.

**ARK must brief (Compact density):**

| Verb | Content |
|------|---------|
| What is happening? | Shop opening — 1 overnight RO, 2 appointments arriving |
| Why? | Since Last Shift boundary |
| What should I do? | Review 3 Attention items |
| Can I trust that? | Items sourced from decision pressure + comms queue |
| What can I do? | Open Attention · Open first appointment RO |

**Surfaces:** Portable Station orientation home (Track B). Same Attention projection as desktop (Orientation Platform).

**Status:** ⚠️ Attention exists on mobile; orientation home not yet default. Opens to Conversations tab today.

---

## 8:10 AM — Customer texts

**Floor:** Sarah Johnson texts: *"Are my brakes ready yet?"* She has RO #5102 waiting on approval.

**ARK must brief (Interrupt → Standard on open):**

| Verb | Content |
|------|---------|
| What is happening? | Customer asked if brakes are ready |
| Why? | RO #5102 · 2019 F-150 · Waiting approval · $2,840 |
| What should I do? | Reply with status |
| Can I trust that? | Last shop message sent estimate link · customer viewed twice |
| What can I do? | Reply · Call · Open RO · Send payment link (when approved) |

**Flow:**

1. Push arrives: Sarah Johnson — *Are my brakes ready yet?*
2. Edward taps → already oriented (not raw inbox)
3. Edward replies from Portable Station
4. `ConversationMessage` written — desktop thread shows same truth
5. `current_situation` updates on RO orientation

**Surfaces:** Push (B) · Conversation thread (B) · RO orientation (C) · Desktop Customer Hub (C)

**Status:** ⚠️ Thread + reply ✅ · Push stubbed · Mark read missing · Orientation on open partial

**Acceptance:** Complete when tap → oriented thread → reply → desktop parity → situation updates without Edward searching.

---

## 8:30 AM — Phone rings

**Floor:** Incoming call from unknown number matching open RO customer.

**ARK must brief (Interrupt):**

| Verb | Content |
|------|---------|
| What is happening? | Incoming call · Mike Kindig |
| Why? | RO #5098 · Vehicle in shop · Waiting on parts |
| What should I do? | Answer (Front Counter) or Callback (on lot) |
| Can I trust that? | Customer texted 20 minutes ago |
| What can I do? | Answer · Callback · Open conversation |

**Surfaces:** Front Counter VVX (A) · Call pop desktop (C) · Portable awareness (B) — not softphone yet

**Status:** ⚠️ Desktop call pop ✅ · Mobile call awareness 🔲 · Flutter SIP out of scope until Front Counter certified

---

## 9:15 AM — Landon needs help

**Floor:** Landon at Bay 3 yells: *"Need second set of eyes on brakes — customer waiting."*

**ARK must brief:**

| Verb | Content |
|------|---------|
| What is happening? | Landon requested advisor at Bay 3 |
| Why? | RO #5098 · Production blocked · Customer waiting |
| What should I do? | Walk to bay or reply on internal channel |
| Can I trust that? | Inspection complete · Parts on RO |
| What can I do? | Open RO · Message Landon · Page (later) |

**Surfaces:** Internal operational messaging (B + A authority) · RO workspace (B/C)

**Status:** 🔲 Desktop internal channels exist · Mobile internal 🔲

**Acceptance:** Edward receives push → opens RO at production orientation → replies to Landon without Slack-style thread navigation.

---

## 10:00 AM — Warranty approves

**Floor:** Warranty company approves deferred work on overnight RO #4821.

**ARK must brief:**

| Verb | Content |
|------|---------|
| What is happening? | Warranty approved recommended work |
| Why? | RO #4821 · Was waiting 2 days |
| What should I do? | Notify customer · schedule production |
| Can I trust that? | Approval recorded in authority |
| What can I do? | Text customer · Assign tech · Open RO |

**Surfaces:** Attention (C) · RO orientation (C) · Portable home row updates (B)

**Status:** ⚠️ Decision pressure on Attention ✅ · Portable home refresh 🔲

---

## 12:00 PM — Walk to Front Counter

**Floor:** Edward was operating Portable Station near Bay 2. He walks to Front Counter and unlocks the desk.

**ARK must reflect:**

| Entity | State |
|--------|-------|
| Front Counter | Operator: Edward |
| Portable Station | Still Edward's device — station context may differ |
| Comms routing | May follow operator (later) |

**Surfaces:** Workstation operator (A) · Station orientation desktop (C) · Portable presence UI (B)

**Status:** 🔲 Operator assignment exists on desktop · Portable station switch 🔲

---

## 2:30 PM — Send estimate from lot

**Floor:** Customer approves verbal add-on. Edward sends estimate link from phone beside vehicle.

**ARK must brief:**

| Verb | Content |
|------|---------|
| What is happening? | Additional work recommended |
| Why? | RO #5110 · Customer on site |
| What should I do? | Send estimate link |
| Can I trust that? | Inspection findings captured |
| What can I do? | Send estimate · Text · Open RO |

**Status:** ✅ `POST send-estimate` from mobile · Composer action in thread ✅

---

## 5:45 PM — End of day

**Floor:** Two ROs waiting on customer decision. One call unhandled from 4 PM.

**ARK must brief (Attention Standard):**

| Verb | Content |
|------|---------|
| What is happening? | 2 decisions pending · 1 comms item missed |
| Why? | Dollars and age on each row |
| What should I do? | Day Review queue before leaving |
| Can I trust that? | Workflow truth from posted/open ROs |
| What can I do? | Open each row · Mark handled |

**Surfaces:** Attention / Day Review (C) · Portable Attention tab (B)

**Status:** ⚠️ Mobile Attention ✅ · Day Review surface desktop only

---

## Advisor scenario checklist

| Time | Scenario | Status |
|------|----------|--------|
| 7:55 | Orientation home unlock | 🔲 |
| 8:10 | Customer text → push → oriented reply | ⚠️ |
| 8:30 | Incoming call awareness | ⚠️ |
| 9:15 | Internal help request | 🔲 |
| 10:00 | Warranty approval updates pressure | ⚠️ |
| 12:00 | Station switch Front Counter | 🔲 |
| 2:30 | Send estimate from lot | ✅ |
| 5:45 | Day Review / end of day | ⚠️ |
