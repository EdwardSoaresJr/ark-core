# Screen spec — Payment Sheet

**ID:** `companion.screen.payment-sheet`  
**Role(s):** Advisor  
**ARK doctrine:** `ark-square-payments.mdc` — server-authoritative balance  
**Status:** 📝 draft — Edward review

---

## Job

Collect payment on an **issued invoice** — cash · card · terminal · payment link — balance from server only, never duplicated in JavaScript.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Generic POS keypad · CRM payment tile | **Target: Yes** |
| **Why** | Standalone POS app metaphor | **RO-linked invoice** · customer · vehicle · one sheet from search · thread · RO workspace |

---

## Layout (production spec)

### Presentation

- **Bottom sheet** (default) — draggable · half → full height
- **Full-screen** when Square Terminal pairing needs space — rare

### Header — context (always visible)

- Customer · vehicle
- **RO #1599** · invoice #
- **Balance due** — Display · authoritative from `EstimateTotalsCalculator` / invoice projection
- Paid to date · last payment — muted if partial


- Default amount = **full balance due** (editable)
- Numeric keypad — large tap targets
- **Quick amounts:** Full balance · Custom
- No arbitrary "sale items" — this is invoice collection, not retail POS

### Payment methods row

| Method | Behavior |
|--------|----------|
| **Cash** | Amount → confirm → record ledger entry |
| **Card (keyed)** | Square keyed flow · amount locked |
| **Terminal** | Pair/select terminal · send to device · wait for capture |
| **Send link** | Portal pay token → SMS via [`compose-reply-sheet.md`](compose-reply-sheet.md) pattern |

Methods hidden when shop settings disable them — configuration, not hardcoded.

### Footer

- **Record payment** — primary when method + amount valid
- **Cancel** — dismiss sheet · no mutation

### Success state (inline — not new screen hunt)

- Checkmark · new balance · receipt actions
- **Text receipt** · **Email receipt** (P1) · **Done**

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Change amount | Keypad · cannot exceed balance without override (P1 manager) |
| Tap method | Highlight · show method-specific sub-step |
| Terminal | Poll server for capture status · cancel sends void to terminal API |
| Send link | Opens composer pre-filled or sends immediately with confirm |
| Swipe down | Dismiss if no payment in flight |
| Done | Return to caller — RO workspace · search · thread |

---

## States

| State | UX |
|-------|-----|
| No invoice | Banner "Issue invoice first" · link to RO workspace |
| Zero balance | "Paid in full" · dismiss |
| Partial paid | Show remaining · history link |
| Terminal pending | Spinner · "Waiting on terminal…" |
| Failed capture | Error inline · retry |
| Offline | Disable card/terminal · cash only if policy allows · banner |

---

## Flows

**Entry:**

- [`global-search.md`](global-search.md) → Pay
- [`repair-order-workspace.md`](repair-order-workspace.md) → Take payment
- [`conversation-thread.md`](conversation-thread.md) Manage → Take payment
- [`active-call.md`](active-call.md) Pay tool (sheet over call)

**Exit:**

- Success → stay on RO or pocket · **never** dump to Home

Link: [`../02-flows.md`](../02-flows.md#take-payment-from-search)

---

## Data & API

**Authority:** Financial domain · `RecordLedgerEntryAction` path — same as desktop

**Needs:**

- Invoice balance projection per RO
- Payment method availability from shop settings
- Square terminal session endpoints (existing desktop patterns)

**May need:** `GET /api/mobile/repair-orders/{id}/payment` · `POST .../payments` · terminal status poll

**Never:** Client-side tax/total recalculation

---

## Edward sign-off

- [ ] Faster than opening desktop to take payment at counter
- [ ] Balance always matches RO show
- [ ] Ready for Flutter
