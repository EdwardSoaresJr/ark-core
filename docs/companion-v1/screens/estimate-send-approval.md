# Screen spec — Estimate Send & Approval

**ID:** `companion.screen.estimate-send-approval`  
**Role(s):** Advisor  
**Status:** 📝 draft — Edward review

---

## Job

**Send estimate link** or **record approval** on the RO — from thread · RO · post-call without desktop.

---

## Presentation

- Bottom sheet · two entry modes share one spec

---

## Mode A — Send estimate

- RO · customer · vehicle
- Estimate total — authoritative
- Delivery: **SMS** (default) · copy link
- Preview line — what customer receives
- **Send** → `ConversationMessage` with portal link · system row in thread

Same authority as desktop `SendEstimateLinkAction`

---

## Mode B — Capture approval

- Concern or full RO scope selector
- Approved · Deferred · Declined per concern or batch
- Required deferred reason when shop policy requires
- **Save** → lifecycle projection updates

---

## Flows

Thread quick action → Mode A  
RO workspace → Send estimate  
Customer called → Mode B after verbal approval

---

## Edward sign-off

- [ ] Same outcomes as desktop Quick Reply + RO approve
- [ ] Ready for Flutter
