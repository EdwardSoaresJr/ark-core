# ARK Communications v3 — Research Sprint

**Status:** Research only · **No code**  
**Date:** 2026-07-15  
**Trigger:** Operator uncertainty — “Does this land in Needs Attention or Conversations?”

**Companions:** [ark-conversations-v1.md](ark-conversations-v1.md) (frozen — do not edit for wording) · Attention Queue doctrine · Communications foundational doctrine

---

## The gate question (answer first)

> **If Sarah texts the shop right now, where should Edward instinctively look, and why is there only one correct answer?**

**Answer:** Edward opens **Communications** — the single relationship inbox — and looks at the top section: **Needs You** (Waiting on Shop).

There is only one correct answer because every serious product studied below makes the operator’s first look a **who-needs-me list of people**, not a menu of destinations. SMS, call, voicemail, portal, and estimate events are **reasons inside that person-row**, not alternate homes for the same work.

Until ARK’s chrome matches that sentence, every new channel feature will make the inbox feel more confusing, not more capable.

---

## Assignment scope

**Study workflow, not UI.** Ignore colors, icons, density theater.

Products:

| Tier | Products |
| --- | --- |
| **1 (must)** | Quo (OpenPhone) · GoHighLevel · Front · Missive |
| **2** | Intercom · Help Scout · Zendesk Messaging · Linear Inbox · Superhuman · Apple Messages · Gmail |

Questions answered as **behavior**, not feature catalogs.

---

## Cross-product lifecycle (arrival → history)

### Shared pattern (the market consensus)

```text
Inbound lands in ONE conversation / contact thread
        ↓
Appears in ONE primary work list (Open / Unread / Needs You)
        ↓
Operator is notified (badge · interrupt · count)
        ↓
Ownership is claim / assign / “anyone on the shared inbox”
        ↓
Completion = explicit status change (Done · Archive · Close · Resolve)
        OR computed turn flip after reply (product-dependent)
        ↓
History = same thread, sunk under Done / All / Search
```

**Never** observed as a healthy pattern:

```text
Decide: Is this Attention OR Inbox OR Calls OR Portal OR Voicemail?
```

That decision is product architecture leaking into operator cognition.

---

## 1–10 answers by product family

### Quo (OpenPhone)

| Question | Behavior |
| --- | --- |
| **1. New call** | Appears in the **same contact conversation** as texts. Live interrupt + inbox row. Completes as call event in timeline; missed/unanswered stays **Open / Unresponded** until marked Done or callback handled. |
| **2. New SMS** | Same conversation object. Primary signal = **Unread** (blue dot) + Open. Not a separate SMS inbox. |
| **3. Voicemail** | Attaches to the call/conversation. Filterable (Voicemail / Missed) but **still the same chat thread**. Not a parallel “VM product.” |
| **4. Stop being attention** | **Done** removes from main view. Unread → read on open (still Open). Unresponded is separate from Unread (viewed ≠ replied). |
| **5. After reply** | Often still **Open** until operator marks **Done**. Reply alone does not always = archive. |
| **6. Primary list** | **Chats / Inbox** for that number — Open conversations. |
| **7. Active vs history** | Open vs **Done** (filter). Same thread forever. |
| **8. Duplicate work** | Shared status syncs team-wide; Done for one is Done for all on that inbox. |
| **9. Ownership** | Shared number inbox; teammate filters; comments. Ownership is light vs Front/Missive. |
| **10. Never ask** | Which channel tab? Whether this missed call is a different object from Sarah’s thread. |

### GoHighLevel

| Question | Behavior |
| --- | --- |
| **1–3** | **Conversations** tab = unified contact timeline (SMS, email, call, social, activities). Call/VM are timeline entries, not separate first homes. |
| **4** | **Unread** until reply **or** explicit Mark Read (opening does **not** auto-clear). Archive sinks. |
| **5** | Reply marks read; conversation can remain in All/Recents until archived. |
| **6** | **Conversations** — Unread / Recents / All / Starred filters. |
| **7** | Recents/All vs Archive. |
| **8–9** | Assignees + filters; star as personal pin. Automation (workflows) can mark read/archive — risk of noise if misconfigured. |
| **10** | Never ask “SMS inbox vs Call inbox” as the first job. |

### Front

| Question | Behavior |
| --- | --- |
| **1–3** | Shared inbox conversations absorb channel traffic. Status moves **Open ↔ Later (snooze) ↔ Done**. |
| **4** | Archive / Resolve / Snooze / Assign. Team actions are explicit and visible (“who will be affected”). |
| **5** | Reply does not always clear; **archive/resolve** is the clear job. Customer reply can reopen. Closed (ticket mode) can **split** new inbound into a new conversation. |
| **6** | **Open** (Assigned to me + shared open). |
| **7** | Open / Later / Done. |
| **8** | Shared archive clears queue for teammates; assignment moves work out of unassigned. |
| **9** | Assignment is first-class. |
| **10** | Never ask channel bucket; ask **status + assignee**. |

### Missive

| Question | Behavior |
| --- | --- |
| **1–6** | Team Inbox = shared triage queue. Assign → personal Inbox + Tasks. **Close** = assignment complete; new reply **reopens to assignee**. Archive clears view without “done” task semantics. |
| **7–9** | Inbox vs All / Closed. Assignment prevents silent double-work. |
| **10** | Never ask “email vs SMS product”; ask **triage → assign → close**. |

### Intercom · Help Scout (Tier 2)

| Behavior |
| --- |
| Open / Snoozed / Closed · Assign to teammate or team · Customer reply reopens · One conversation timeline · Snooze = “not now, still work.” |

### Gmail · Superhuman · Apple Messages · Linear Inbox (Tier 2 metaphors)

| Product | Metaphor ARK should steal |
| --- | --- |
| **Gmail** | One list; labels are filters, not competing homes. Inbox vs Archive. |
| **Superhuman** | Keyboard completion (Done); unread ≠ unreplied discipline. |
| **Apple Messages** | Person-first threads; channels invisible. |
| **Linear Inbox** | Notifications as triage; clearing moves to history without a second “notification product.” |

---

## Behavioral scoreboard (what masses love)

| Principle | Quo | GHL | Front | Missive | Intercom/HS | Gmail |
| --- | --- | --- | --- | --- | --- | --- |
| One primary look | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Contact/timeline unity | ✓ | ✓ | ✓ | ✓ | ✓ | ≈ |
| Explicit Done/Archive | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Reply ≠ always Done | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Customer reply reopens | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Assignment (team) | light | medium | strong | strong | strong | weak |
| Separate Calls product as first door | ✗ | ✗ | ✗ | ✗ | ✗ | n/a |

---

## ARK today — gap analysis

### What ARK already got right (authority)

- Conversation / Message / CallSession / CommunicationEvent authorities
- Doctrine: **One Thread · One Story · One Turn · Reason-led rows** ([ark-conversations-v1.md](ark-conversations-v1.md))
- Calls & VM as **evidence** surface is correctly protected
- Companion doctrine: inbox-first advisor product

### What ARK gets wrong (projection / chrome)

Current Comms section nav (operator-visible):

```text
Needs Attention
Conversations
History
Calls & VM
Internal
```

That chrome asks the forbidden question:

> *Where did this land?*

| Market behavior | ARK behavior today |
| --- | --- |
| One inbox | Split Attention vs Conversations vs History |
| Person + Reason row | Correct in Conversations doctrine; diluted by dual homes |
| Done / Archive sinks | Status filters exist in doctrine; Attention queue feels like a **second product** |
| Calls in thread | Calls & VM is necessary evidence, but becomes a **competing first door** |
| Interrupt ≠ recovery | Topbar interrupt vs Attention recovery is correct **if** Conversations is the daytime home — today both “look like inbox” |

### Root cause

**Authority is relationship-native. Navigation is still subsystem-native.**

Needs Attention was built as **morning recovery projection** (doctrine: interrupt vs recovery). Conversations was built as **operational memory**. Together, without a single Communications shell, operators experience two inboxes.

---

## Recommended ARK communication lifecycle (v3 model)

**Do not edit frozen Conversations doctrine wording.** This is a **projection shell** proposal that implements The Six Ones.

```text
COMMUNICATIONS                          ← one product name
│
├─ Needs You                            ← Turn = Waiting on Shop (computed)
│    Sarah · Customer replied · 2m
│    John · Missed call · 11m
│    Emily · Portal approved · 24m
│
├─ Waiting on Customer                  ← Turn = Waiting on Customer
│
└─ Everything Else                      ← Resolved / Archived / search / done
     (same Thread · same Story · sunk)
```

**Workspace (unchanged obligation):** Identity → Story (+ Evidence) → Shop Context → Actions.

**Calls & VM:** Remains reachable as **evidence / library** (recordings, voicemail playback) — never as the place Edward must choose when Sarah texts.

**Interrupts (topbar / Companion push):** Still interrupt. They deep-link into the **same** Communications thread — never into a third inbox.

### Lifecycle table (ARK v3)

| Stage | Rule |
| --- | --- |
| **Arrival** | Any inbound (SMS, call, miss, VM, portal, estimate view) updates **one Thread** and recomputes **Turn + Reason**. |
| **Notification** | Badge/count on **Communications · Needs You**. Live call keep interrupt. |
| **Inbox placement** | Always **Needs You** when Turn = Waiting on Shop. Never “put in Calls instead.” |
| **Ownership** | Advisor claim / assignee on Thread (already advisor-first). |
| **Completion** | Status → Resolved / Done **or** Turn flips after shop act + customer not waiting. Prefer one obvious Done for shop floor. |
| **History** | Same Thread under Everything Else / search. Story immutable. |

### Attention stops when

Prefer **computed Turn** (doctrine) over “mark read”:

| Signal | Effect |
| --- | --- |
| Shop replies / handles commitment | Turn may become Waiting on Customer |
| Operator marks Resolved/Done | Leaves Needs You |
| Customer writes again | Re-enters Needs You (reopen) |
| Merely opened / viewed | Does **not** clear Needs You (align with GHL / Unresponded ≠ Unread) |

---

## What ARK must NEVER ask the operator to decide

1. Needs Attention **or** Conversations?
2. Calls **or** Conversations for a missed call?
3. Portal event — which inbox?
4. Which channel “owns” Sarah?
5. Is this a message product or a telephony product?

---

## Stop rule (before any more communications code)

**Stop** polishing Companion UI chrome, additional Attention sources, and dual-nav refinements until:

1. The Sarah sentence is true on the floor without training.
2. One Communications list is the daytime primary.
3. Needs You is a **section/filter of Turn**, not a sibling destination that competes with Conversations.

**Allowed meanwhile:** Evidence bugfixes, transport reliability, recording playback on Calls & VM as library, doctrine observation notebooks.

---

## Sources (workflow docs, not screenshots)

- Quo: Conversation status · Inboxes overview · Views/filters  
- Front: Conversation status · Inbox sections · Tabs  
- Missive: Team inboxes · Triage & assignment · Conversations FAQ  
- GoHighLevel: Conversations tab · Read status · Filters / Edit Conversation  
- Intercom / Help Scout: Open · Snooze · Close · Assign  

---

## Outstanding questions for floor notebook (not for code yet)

1. For Demo Auto Repair volume: Should **Done** be manual (Quo) or mostly **Turn-computed** (ARK doctrine)?
2. Does morning **Attention recovery** merge into Communications · Needs You, or remain a thin “since last shift” banner *inside* Communications?
3. After one shell lands: keep Calls & VM in section nav as Evidence, or nest under thread Evidence + a library route?

---

**End of research sprint. No implementation in this document.**
