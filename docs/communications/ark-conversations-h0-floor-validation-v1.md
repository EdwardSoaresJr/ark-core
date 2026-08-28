# Conversations H0.4 — Floor Validation Protocol

**Gate:** Required after H0.2 / H0.2.1 / H0.3 green · **before any H1 chrome**  
**Doctrine:** [ark-conversations-v1.md](ark-conversations-v1.md)  
**Precedence:** Newest unresolved inbound customer communication owns Waiting on Shop (`ConversationTurnPrecedence`)

> H0.3 is closed in engineering. This gate is **not** about whether Pest passes.  
> It is whether posture matches how Molly naturally reads the thread — without reconstructing transports.

---

## Script (do not explain ARK)

Tell Molly (or the on-duty advisor):

> Use Conversations. Look at whose turn it is. Say out loud every time something feels weird or wrong.

Write down every hesitation.

**Do not** defend the software.  
**Do not** explain why it works that way.  
Just write.

---

## Validation set (walk these on the floor)

| # | Situation | Expected Turn |
| --- | --- | --- |
| 1 | Missed inbound call with no follow-up | Waiting on Shop |
| 2 | Customer text after an outbound shop text | Waiting on Shop |
| 3 | Advisor replies by SMS after an inbound call | Waiting on Customer |
| 4 | Outbound call after customer message | Waiting on Customer |
| 5 | Older unresolved inbound exists, but a newer shop response resolves it | Waiting on Customer (older inbound resolved) |
| 6 | Mixed thread: SMS + Messenger + call — newest unresolved inbound wins | Waiting on Shop |
| 7 | Worked call does not leave stale Waiting on Shop | Waiting on Customer (when no newer unresolved inbound) |
| 8 | No communication history | No false posture (no invented “who owes what”) |

Transport must never be what Molly has to invent. She should see **who owes the next move**.

---

## Pass condition (simple)

**Molly can look at the thread and immediately agree with who owes the next move.**

- If she hesitates → gate **RED**. H1 stays blocked.  
- If she consistently agrees → H0 can **close** and H1 may begin.

Also: she never asks **“Where did that message go?”**

Within ~five seconds she knows: who · why · what happened · what next.

---

## Classify each hesitation

| Bucket | Meaning |
| --- | --- |
| **Discoverability** | Could not find who / where |
| **Workflow** | Knew what they wanted; path fought them |
| **Vocabulary** | Words on screen did not match shop language |
| **Confidence** | Unsure if the turn / action was right |

If you find yourself explaining the software — log it as Confidence or Vocabulary. That is evidence the software is not explaining itself.

---

## Notebook entry (required to close H0)

```text
Date:
Advisor:
Sessions walked: (checklist 1–8)

Hesitations:
-

Verdict: PASS / FAIL
Molly agrees with who owes the next move without hesitation? Y / N
```

Only a **PASS** notebook entry unlocks H1.

---

## Explicit non-goals during H0.4

- Do not start H1 chrome  
- Do not “fix” hesitations mid-session by redesigning UI  
- Do not ship appointment SMS / AI summaries as a substitute for this gate  
- Do not argue with Molly about doctrine — write what she said
