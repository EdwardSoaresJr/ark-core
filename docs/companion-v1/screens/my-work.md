# Screen spec — My Work (Technician)

**ID:** `companion.screen.my-work`  
**Role(s):** Technician  
**ARK doctrine:** `ark-technician-scope.mdc`  
**Status:** 📝 draft — Edward review

---

## Job

**Assigned ROs only** — what Ben opens first · no shop-wide queue · no comms inbox.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | CRM task lists | **Target: Yes** |
| **Why** | Generic tasks | **Assigned production work** · vehicle-first rows |

---

## Layout

### Header

- **My Work** · operator name
- Bay / station chip if set — `Bay 3`

### List — assigned ROs only

**Row:**

- Vehicle YMM — primary
- RO # · concern count · status chip
- Advisor name · promised time
- Badge: inspection in progress · parts waiting

### Empty

- "No assigned work" · calm · check with advisor

### Tab bar (tech)

- **My Work** · Active RO (P1) · More

**No:** Communications tab · Global search · Schedule · Payments

---

## Flows

Launch (tech role) → My Work → tap RO → RO workspace (tech mode) → inspection

---

## Data & API

**Needs:** `GET /api/mobile/my-work` — assigned ROs for current user only

---

## Edward sign-off

- [ ] Ben never sees shop-wide workboard
- [ ] Ready for Flutter
