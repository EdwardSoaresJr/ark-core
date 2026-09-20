# Certification record — Vehicle Pickup Workflow

**Certification:** Vehicle Pickup Workflow  
**Track:** B — ARK Staff · Workflow 4  
**Role:** Advisor  
**Owner:** Alex Rivera  
**Scenario source:** Customer picks up · phone only

## Why this matters

Advisor completes pickup at the counter or curb — invoice, payment, receipt, RO close — without desktop. Customer leaves; history is updated.

---

## PASS levels

| Level | Status | Date | Signed by |
|-------|--------|------|-----------|
| Engineering Certified | ⬜ | | |
| Operationally Certified | ⬜ | | |
| Production Certified | ⬜ | | |

---

## Operational acceptance — phone only

```
Customer (ready vehicle)
        ↓
Invoice
        ↓
Payment
        ↓
Receipt
        ↓
Close RO
        ↓
Done
```

| Step | Acceptance | Status | Proof |
| --- | --- | --- | --- |
| Ready vehicle found | From continuity or customer workspace | ⬜ | |
| Invoice / balance visible | Server-authoritative totals | ⬜ | |
| Payment captured | Square / configured gateway | ⬜ | |
| Receipt / confirmation | Customer + record | ⬜ | |
| RO closed | Lifecycle complete | ⬜ | |
| History updated | Visible on desktop after phone close | ⬜ | |
| Desktop never required | | ⬜ | |

---

## Corrections

- *(append only)*
