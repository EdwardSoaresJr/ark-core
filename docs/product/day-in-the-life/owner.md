# Owner / Manager — Molly (admin)

**Station:** Office + Portable Station for floor walks  
**Primary question:** What needs attention across the shop?

Molly is an owner-operator. She needs **daily numbers and queue truth**, not a second ERP dashboard. Mobile is for pulse checks on the lot; Day Review and Operational Report remain desktop-weight surfaces.

---

## 7:30 AM — Shop pulse

**Floor:** Before advisors arrive. Molly checks overnight activity from home.

**ARK must brief:**

| Verb | Content |
|------|---------|
| What is happening? | 1 overnight RO · 0 calls unhandled · 2 decision rows aging |
| Why? | Attention composition from authority |
| What should I do? | Scan Since Last Shift |
| Can I trust that? | Same projections as advisor Attention |
| What can I do? | Open Attention rows · drill to RO |

**Surfaces:** Portable Attention (B) · Desktop Attention (C)

**Status:** ⚠️ Mobile Attention ✅ for admin role · No owner-specific digest on mobile

---

## 11:00 AM — ELR leak on floor walk

**Floor:** Molly overhears free diagnostic conversation. She checks effective labor rate context later on desktop.

**ARK must brief (management — desktop primary):**

| Verb | Content |
|------|---------|
| What is happening? | Open ROs diluting billed hours |
| Why? | Operational report / shop excellence targets |
| What should I do? | Review closed work vs open diag (desktop) |
| Can I trust that? | Posted RO truth |
| What can I do? | Open report · adjust targets in settings |

**Surfaces:** Operational Report (desktop) — **not a mobile v1 scenario**

**Status:** 🔲 Mobile does not expose financial/ELR surfaces (correct per scope)

**Feature gate:** If a proposal does not appear in Molly's mobile day, reject mobile scope — do not shrink reports onto phone.

---

## 3:00 PM — Customer escalation

**Floor:** Customer calls shop asking for manager. Advisor escalates.

**ARK must brief:**

| Verb | Content |
|------|---------|
| What is happening? | Customer escalation on RO #5088 |
| Why? | Deferred work dispute · 14 days |
| What should I do? | Review RO + conversation before calling back |
| Can I trust that? | Full timeline on RO |
| What can I do? | Open RO · Open conversation · Call back |

**Surfaces:** Attention → RO → conversation (B/C)

**Status:** ⚠️ Path exists on desktop · Portable path partial (no manager home orientation)

---

## 5:30 PM — Day Review

**Floor:** Shop closing. Molly reviews queue with advisors.

**ARK must brief:**

| Verb | Content |
|------|---------|
| What is happening? | 4 open decision rows · 2 comms unhandled |
| Why? | End-of-day Day Review queue |
| What should I do? | Clear or assign follow-ups for tomorrow |
| Can I trust that? | Same Attention authority as morning |
| What can I do? | Day Review review (desktop) · assign owners |

**Surfaces:** Day Review `/app/owner/day-review` (desktop) · Attention (mobile partial)

**Status:** ⚠️ Day Review desktop only · Mobile Attention is substitute, not Day Review

---

## 6:00 PM — Owner digest email

**Floor:** Automated digest if enabled in shop targets.

**ARK must brief:** N/A — async email from `shop-excellence:owner-digest`

**Surfaces:** Email · Settings → Owner Targets

**Status:** ✅ Server-side digest exists · Not mobile

---

## Owner scenario checklist

| Time | Scenario | Mobile status |
|------|----------|---------------|
| 7:30 | Morning pulse | ⚠️ |
| 11:00 | ELR / financial review | 🔲 desktop only (intentional) |
| 3:00 | Escalation drill-down | ⚠️ |
| 5:30 | Day Review | 🔲 desktop |
| 6:00 | Owner digest email | ✅ email |

**Rule:** Owner mobile scenarios are **orientation and attention**, not reporting parity. Measure progress by escalation and pulse scenarios, not by shipping dashboards on phone.
