# SMS Operational Proof — Shop Gate (Step 5)

**Purpose:** Prove SMS ingress is daily-use before adding Messenger. Code Steps 1–4 are deployed; this gate is **operational**, not a build step.

**Duration:** Minimum 2 weeks of real shop traffic, or until all pass criteria hold for 5 consecutive business days.

---

## Pass criteria (all must be true)

| # | Criterion | How to verify |
|---|-----------|---------------|
| 1 | Inbound SMS lands on **Customer Hub** and **RO Review** conversation rail without manual copy-paste | Send test text from a known customer phone; confirm message appears on hub + open RO within 30s |
| 2 | Advisors reply from **customer/RO context**, not only the raw queue | Reply from Customer Hub composer or queue “Reply” link; confirm outbound appears on same thread |
| 3 | **Comms Queue** is morning triage — opened at shift start, items cleared or intentionally deferred | Advisor lead signs queue reviewed daily |
| 4 | Dominant friction is **not** “where did that text go?” | Friction log (below) — no more than 1 “lost message” per week |

---

## Daily advisor rhythm (5 minutes)

1. Open **[Work](/app)** — triage **Customer Decisions**, **Since Last Shift**, then **Needs Attention**.
2. For matched customers: open **Customer Hub** or **RO Review** from queue row; reply in context.
3. For unknown numbers: **Lookup Caller** → intake or link customer before replying.
4. Mark read when handled (queue or conversation rail).

---

## Weekly friction log (T0 notebook)

Record one line per incident. Dominant pattern after 2 weeks decides Deep Memory / UX work — not Messenger.

| Date | Customer / phone | Friction type | What happened | Surface used |
|------|------------------|---------------|---------------|--------------|
| | | lookup / conversation / lost place / wrong context | | queue / hub / RO / lookup |

**Friction types**

- **lookup** — hard to identify who is texting
- **conversation** — thread exists but hard to read or scan
- **lost place** — message not where advisors expect
- **wrong context** — open RO / vehicle / posture wrong on the rail

**Gate fails if:** “lost place” or “wrong context” is the top category for 2+ weeks.

---

## Smoke test script (after deploy or Twilio change)

Run once per environment when credentials or webhook URL changes.

1. Confirm Twilio messaging webhook URL points at production `webhooks/communications/twilio/messaging/incoming`.
2. From a **known customer** mobile, text the shop line: `SMS proof test — [date]`.
3. Within 30s: message on Customer Hub comms tab and open RO conversation rail.
4. Advisor replies from hub: `Reply proof — [date]`.
5. Customer receives SMS; thread shows outbound on hub.
6. Queue: unread clears after mark-read or reply.

---

## When to proceed to Messenger (Step 6)

Proceed when **all** of:

- [ ] Pass criteria 1–4 met for 5 consecutive business days (or 2 full weeks)
- [ ] Friction log shows SMS is findable and contextual
- [ ] Real Facebook DM volume **or** documented lost leads justify a second channel

Messenger adapter is in ARK v2. Enable it in Communications → **Messenger** only after Meta webhook credentials are configured in the target environment.

---

## Escalation to dev

Open a dev ticket when:

- Inbound webhook returns non-200 or message never persists
- Duplicate messages on Twilio retry (idempotency failure)
- MMS/attachments missing or broken
- Reply fails with Twilio error but UI shows success
- Queue count disagrees with unread on hub for same conversation

Include: `MessageSid`, customer phone, timestamp, RO #, screenshot of queue + hub.

---

## Related

- `docs/communications-roadmap-to-messenger.md` — full path to Messenger
- `docs/communications-authority.md` — conversation vs workflow event doctrine
- Learn: **Comms Queue** and **Customer Hub** advisor articles under `/app/learn`
