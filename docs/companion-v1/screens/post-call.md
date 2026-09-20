# Screen spec — Post-Call

**ID:** `companion.screen.post-call`  
**Role(s):** Advisor  
**Status:** 📝 draft — Edward review

---

## Job

Hang up → **one screen** to log, text, schedule, or open RO — **without backing out to Home**.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Notes · calendars · tags · callback · dispositions | **Target: Yes** |
| **Why** | CRM disposition + tags | **Shop outcomes:** note · text · schedule · open RO · mark handled |

---

## Layout

**Header:** Customer · vehicle · RO chip · call duration

**Primary actions (horizontal or 2×2 grid)**

- **Add note** — RO or customer
- **Send text** — opens composer with thread
- **Schedule** — appointment / callback
- **Open RO** — full workspace

**Secondary row**

- **Call back** · **Mark handled** (if from queue)


---

## Interaction patterns

| Pattern | Reference |
|---------|-----------|
| Dismiss to pocket | Swipe down or Done |

---

## Flows

**From:** [`active-call.md`](active-call.md) End  
**Not:** Home · not conversation list unless user chooses Text then stays in thread

---

## Edward sign-off

- [ ] Ready for Flutter
