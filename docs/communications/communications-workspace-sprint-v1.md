# Communications Workspace Sprint v1 — Who Needs a Reply?

**Status:** Locked milestone  
**Effective:** 2026-07-01

**Product sentence (external):** *Never lose a customer because someone forgot to reply.*

**Operational rule (internal):** *Every conversation always has someone whose turn it is.*

**Not building:** Communications v2 · generic CRM clone for shops · AI auto-replies · channel-first inboxes · marketing automation

---

## One question

The first screen is not Communications · Inbox · Threads · Conversations.

It is:

> **Who needs a reply?**

**Needs Attention**

```
Jason Smith
Website Lead · 3 minutes ago
Needs first response
```

Click → composer → send. Done.

---

## Sprint success (locked)

Two criteria. Everything else is secondary.

### 1. Advisor effort — under 30 seconds

Not system latency. **Advisor time.**

```
Website lead arrives
        ↓
Advisor clicks one row
        ↓
Types or selects reply
        ↓
Hits Send
```

No searching. No three screens. No deciding which channel first.

**Floor test:** timed advisor effort from click row → send ≤ **30 seconds**.

### 2. Business KPI — one number only

**Median first-response time:** website lead submitted → advisor sends first outbound.

| | Target |
| --- | --- |
| Baseline (observe) | ~2 hours |
| Sprint goal | **≤ 12 minutes** median |

Measure **only this** for the sprint. Not messages sent, emails, calls, or response rate dashboards.

Instrument:

- Lead submitted timestamp (`leads.created_at` or equivalent event)
- First advisor outbound on same `conversation_id` (`ConversationMessage` or logged call/email — not system auto-replies)

Notebook + query until trusted. No dashboard theater.

---

## Demo that wins the shop owner

```
Website lead submitted
        ↓
Advisor notified immediately
        ↓
One click into the conversation
        ↓
Reply sent in under 30 seconds of advisor effort
        ↓
Median first-response time drops below 12 minutes
```

Direct operational payoff on day one. Revenue follows when the loop is fast.

---

## The slice (full path — incremental after reply works)

Prove reply first. Then extend the same thread:

```
Same thread → Estimate sent → Customer approves → RO updates → Today recommendation retires
```

If first reply is fast and measurable, the rest is incremental — not a separate platform expansion.

---

## Turn-based rhythm (not "resolved")

Real conversations have **whose turn it is** — not unread badges or inbox zero.

```
Advisor sends first response
        ↓
Response started → Waiting for customer
        (leave Needs Attention — not advisor's turn)
        ↓
Customer replies
        ↓
Needs Attention returns
```

| State | Advisor surface |
| --- | --- |
| **Advisor's turn** | Needs Attention |
| **Customer's turn** | Not in Needs Attention — do not bother the advisor |

Do not optimize for "Resolved" or "Inbox empty." Optimize for **turn clarity**.

Every first advisor response should transition: **Needs first response** → **Waiting for customer**.

---

## First screen + composer

### Needs Attention row

- Customer name (or contact label)
- Source hint (Website Lead, SMS, Missed call — secondary)
- Age
- Turn reason (`Needs first response`, `Customer replied`, etc.)

One click opens thread + composer. No intermediate navigation.

### One composer

Not separate SMS UI. Not separate Email UI. **One composer.** Transport changes; conversation stays.

```
Reply

[ SMS ]  [ Email ]  [ Call ]

────────────────────────
Message
_____________________

Quick Replies
  Estimate received · Scheduling · Running behind · Financing · Custom
```

- **Quick Replies** — operational snippets advisors pick and edit. Not AI. Not auto-send.
- **Later:** AI may draft — **not this sprint.** Advisors must become fast first.

---

## Workspace philosophy

| Surface | Rhythm | Question |
| --- | --- | --- |
| **Needs Attention** | Reactive | Who needs a reply? |
| **Today** | Strategic | What should we improve or close this week? |

When Ben sits down: **Needs Attention → Jason → Reply.** RO, Hub, Today open from the thread — not instead of it.

**Growth measures demand. Communications converts it.**

---

## Authority (unchanged)

| Layer | Rule |
| --- | --- |
| **Conversation** | Won — one timeline |
| **ConversationMessage** | All human comms; `transport` = sms · email · phone · … |
| **Lead** | Projects into conversation + Needs Attention — not a parallel inbox |
| **Turn** | Projection on conversation (advisor turn vs customer turn) — not a new authority store |

**Forbidden:** `EmailMessage`, `EmailThread`, `SmsInbox`, `AttentionItem`, AI auto-replies.

---

## Transport

Fastest path wins. Twilio OK. Asterisk not a sprint gate. Advisor does not see transport.

---

## In scope

1. Website lead → **Needs Attention** row (conversation deduped; one row)
2. Immediate advisor notification (existing realtime/poll — no new product)
3. One-click → thread + **one composer** (SMS · Email · Call)
4. **Quick Replies** (static operational templates)
5. **Turn projection** — first send → waiting for customer; inbound → back to Needs Attention
6. Median first-response instrumentation
7. *(Stretch)* Same thread → estimate → approve → RO → Today retires

---

## Out of scope

- AI responses or auto-replies
- Communications v2 / generic CRM clone
- Separate channel UIs
- Facebook · WhatsApp · Apple Messages
- Asterisk migration as dependency
- Growth features (maintenance only)
- Mobile parity (desktop first)
- Dashboard KPIs beyond median first-response

---

## Definition of done

**Must pass:**

1. Website lead → appears in **Needs Attention** without leads-index hunting
2. Advisor: one click → composer → send in **≤ 30 seconds effort** (timed on floor)
3. After send: row leaves Needs Attention (**waiting for customer**)
4. Customer reply: row returns to Needs Attention
5. **Median first-response** query documented; trending toward **≤ 12 minutes**

**Stretch (same sprint if slice time allows):**

6. Estimate from thread → portal approve → RO update → Today recommendation retires

---

## PR discipline

Every PR answers:

1. Did advisor effort to first reply get shorter or clearer?
2. Does turn logic match "whose turn is it?"
3. Any parallel inbox or AI auto-send? (**Must be no.**)

Stop when locked success criteria pass on the floor.

---

## Companions

| Doc | Relationship |
| --- | --- |
| [communications-bounded-context-v1.md](communications-bounded-context-v1.md) | Authority frozen |
| [communications-authority.md](../communications-authority.md) | Conversation + events |
| ark-attention-queue.mdc | Needs Attention doctrine |
| [CURRENT_MILESTONE.md](../engineering/CURRENT_MILESTONE.md) | Active lane |
