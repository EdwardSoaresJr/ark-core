# Screen spec — Outgoing Call (Dialer)

**ID:** `companion.screen.outgoing-call`  
**Role(s):** Advisor  
**Quo ref:** minimal dial pattern — prefer search-first over bare keypad  
**Status:** 📝 draft — Edward review

---

## Job

Call a customer **with context confirmed** — prefer search/name over raw number · show vehicle before connect.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Keypad · Recents · Contacts tabs | **Target: Yes** |
| **Why** | Recents = numbers | Recents = **customer + vehicle** · search primary · confirm sheet before dial |

---

## Layout

### Entry paths

1. **Preferred:** [`global-search.md`](global-search.md) → Call
2. **Thread / customer / RO** header Call button
3. **Dialer tab** (secondary) — Recents · Keypad


**Tabs:** Recents · Keypad · (Contacts merges into Search)

**Recents row:**

- Customer name · time
- Vehicle · RO chip if call tied to RO
- Tap → **confirm sheet** then dial

**Keypad:**

- Standard 0–9 · \* · #
- Number field · backspace
- **Call** green — if unknown number, confirm "Call (512)…?" · offer create customer after

### Confirm sheet (before connect)

- Customer · vehicle · open RO
- **Call** · Cancel

### Ringing → connected

Transition to [`active-call.md`](active-call.md) — same stack

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap recent | Confirm sheet |
| Long press recent | Text instead |
| Keypad call | Confirm if no customer match |

---

## States

| State | UX |
|-------|-----|
| SIP offline | Banner · disable call · settings link |
| Unknown number | Minimal confirm |

---

## Flows

Search → Call → confirm → active → post-call

Thread → Call → active (skip confirm if customer known)

---

## Data & API

**Needs:** recents with customer resolution · click-to-call via existing telephony  
**Existing:** CallSession outbound · mobile voice registration

---

## Edward sign-off

- [ ] Ready for Flutter
