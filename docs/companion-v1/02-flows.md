# Deliverable 2 — Every Tap (Flows)

**Rule:** No code. Only sequences. If a flow needs more than 3 taps for a P0 job, flag it.

**Format:**

```text
Trigger
  ↓ tap/action
Screen
  ↓
...
Outcome (pocket / done / stay in context)
```

---

## P0 flows (Companion Sprint 1)

### Incoming call → pocket

```text
Phone rings (Incoming Call screen — context visible)
  ↓ Answer
Active Call (minimal — customer + vehicle strip)
  ↓ Hang up
Post-Call sheet (same screen stack — no Home)
  ↓ Add note OR Send text OR Open RO OR Schedule
Target workspace
  ↓ Back
Post-Call OR Conversation (never lost)
  ↓ Done
Pocket
```

### Notification → inspection (Ben uploaded)

```text
Push: "Ben uploaded inspection"
  ↓ Tap
Inspection Item (exact item — not Home)
  ↓ Review photo / Reply internal note
  ↓ Back
RO workspace OR pocket
```

### Notification → customer replied

```text
Push: "Emma replied"
  ↓ Tap
Conversation thread (Emma — full context rail)
  ↓ Reply
  ↓ Send
Pocket
```

### Morning unlock

```text
Unlock phone
  ↓ Open Companion
Home (continuity list — not dashboard)
  ↓ Tap "3 customers replied"
Conversation list (filtered: needs reply) OR first thread
  ↓ Handle OR back
Home
  ↓ Lock
Pocket
```

### Search → act (Emma)

```text
Any screen
  ↓ Search affordance (persistent or gesture)
Global Search
  ↓ Type "Emma"
Search Results (Emma Hathorn + vehicle + open RO)
  ↓ Call | Text | Open RO | Schedule | Pay | History
Target action (one tap from results)
  ↓ Complete action
  ↓ Back
Search OR previous workspace
```

### Reply to text (no notification)

```text
Communications tab
  ↓ Thread with badge
Conversation thread
  ↓ Composer focused
  ↓ Send
Pocket (thread stays read)
```

### Photo from tech → advisor reply

```text
Push: "Photo on RO #1599"
  ↓ Tap
Concern detail OR Inspection item (photo visible)
  ↓ Internal note OR Reply to tech
  ↓ Send
RO workspace
  ↓ Pocket
```

### Outgoing call → pocket

```text
Global Search → Emma
  ↓ Call
Confirm sheet (customer · vehicle · RO)
  ↓ Call
Active Call
  ↓ Hang up
Post-Call → pocket OR thread
```

### Send estimate link (from thread)

```text
Conversation thread
  ↓ Quick action · Send estimate
Server sends portal link → ConversationMessage
  ↓ Confirm sent (system row in thread)
Pocket
```

### Tech: inspection item → complete

```text
My Work
  ↓ RO #1599
Inspection overview
  ↓ Incomplete item
Inspection item
  ↓ Capture photo · save finding
  ↓ Mark item complete
Next item OR Submit inspection
  ↓ Pocket
```

### Advisor: Ben uploaded photo

```text
Push: "Ben uploaded inspection"
  ↓ Tap
Inspection item (photo highlighted)
  ↓ Review · internal note OR Open RO
  ↓ Back
RO workspace OR pocket
```

### Approve estimate (verbal)

```text
RO workspace OR thread
  ↓ Capture approval
Estimate send & approval sheet (Mode B)
  ↓ Save
RO workspace · lifecycle chip updates
```

### Take payment (from search)

```text
Global Search → Emma
  ↓ Take Payment
Payment sheet (balance authoritative from server)
  ↓ Record cash / Send link / Terminal
  ↓ Confirm
Customer workspace OR pocket
```

---

## Flow inventory checklist

| Flow | Documented | ≤3 taps to value? | Edward OK? |
|------|------------|-------------------|------------|
| Incoming call → pocket | ✅ draft | | ⬜ |
| Notification → inspection | ✅ draft | | ⬜ |
| Notification → reply | ✅ draft | | ⬜ |
| Morning continuity | ✅ draft | | ⬜ |
| Search → act | ✅ draft | | ⬜ |
| Photo from tech | ✅ draft | | ⬜ |
| Outgoing call | ✅ draft | | ⬜ |
| Send estimate link | ✅ draft | | ⬜ |
| Tech: inspection item | ✅ draft | | ⬜ |
| Approve work | ✅ draft | | ⬜ |
| Owner: morning pulse | ✅ draft | P1 | ⬜ |
| Login cold start | ✅ draft | | ⬜ |
| Check-in → RO | ✅ draft | P1 | ⬜ |
| Walk-in intake | ✅ draft | P1 | ⬜ |

### Login cold start

```text
Launch app
  ↓ Splash · restore session OR
Login · email/password
  ↓ First run: notification + phone permissions
Advisor → Home continuity
Technician → My Work
```

### Check-in → RO (P1)

```text
Schedule tab → appointment row
  ↓ Check in
Confirm customer · vehicle · concern
  ↓ Create or link RO
RO workspace
```

### Walk-in intake (P1)

```text
More → New walk-in OR Search → no results
  ↓ Customer + vehicle + concern
  ↓ Start RO
RO workspace (draft)
```

---

## Anti-patterns (reject in review)

- Tap notification → **Home** → hunt
- Answer call → **lose customer context**
- Search → customer profile only → **hunt for RO**
- Back button ambiguity (which scaffold owns back?)
- Any flow that dumps to tab root without finishing the job
