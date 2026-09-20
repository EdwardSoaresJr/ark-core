# Scope Membership v1

**Status:** E0b **complete** — mechanical only; no new product ideas.  
**Prerequisites:** [`event-contracts-v1.md`](event-contracts-v1.md) · [`ark-authority-interaction-map-v1.md`](../ecosystem/ark-authority-interaction-map-v1.md) · [`ark-scoped-event-streams-v1.md`](../ecosystem/ark-scoped-event-streams-v1.md).  
**Rule:** If this table disagrees with a signed contract, **the contract wins**. Update this row — do not debate in E0b.

Scope columns map to **Event Stream Engine** inputs (infrastructure — not authorities).

| Column | Stream |
|--------|--------|
| **Customer** | Customer Stream |
| **RO** | RO Stream |
| **Vehicle** | Vehicle Stream |
| **Operator** | Operator Stream |
| **Shop** | Shop Stream |
| **Audit** | Audit-only (no operator stream) |

---

## Engine query (reference)

```text
Customer Stream(customer_id) =
    contracts WHERE Customer = ✅
              AND customer anchor matches
              AND active
    ORDER BY occurred_at
```

---

## Full membership matrix (v1 catalog)

Derived from event-contracts-v1.md scope columns only.

| Event verb | Domain | Cust | RO | Veh | Oper | Shop | Audit |
|------------|--------|:----:|:--:|:---:|:----:|:----:|:-----:|
| **RO Created** | Repair Order | ✅ | ✅ | | | ✅ | |
| **RO Started** | Repair Order | | ✅ | | | ✅ | |
| **Waiting Approval** | Repair Order | ✅ | ✅ | | | ✅ | |
| **RO Approved** | Repair Order | ✅ | ✅ | | | ✅ | |
| **RO Ready** | Repair Order | ✅ | ✅ | | | ✅ | |
| **RO Closed** | Repair Order | ✅ | ✅ | | | | |
| **Technician Blocked** | Repair Order | | ✅ | | | ✅ | |
| **Part Backordered** | Repair Order | | ✅ | | | ✅ | |
| **Inspection Started** | Inspection | | ✅ | ✅ | | | |
| **Finding Added** | Inspection | | ✅ | ✅ | | | |
| **Inspection Completed** | Inspection | ✅ | ✅ | ✅ | | ✅ | |
| **Inspection Published** | Inspection | ✅ | ✅ | | | ✅ | |
| **Message Sent** | Message | ✅ | ✅ | | | | |
| **Message Received** | Message | ✅ | ✅ | | | ✅ | |
| **Call Started** | Call | ✅ | | | | ✅ | |
| **Call Answered** | Call | ✅ | | | | | |
| **Call Missed** | Call | ✅ | | | | ✅ | |
| **Call Completed** | Call | ✅ | | | | | |
| **Voicemail Received** | Call | ✅ | | | | ✅ | |
| **Call Transferred** | Call | ✅ | | | ✅ | ✅ | |
| **Payment Requested** | Financial | ✅ | ✅ | | | | |
| **Payment Received** | Financial | ✅ | ✅ | | | ✅ | |
| **Refund Issued** | Financial | ✅ | ✅ | | | | ✅ |
| **Balance Due** | Financial | ✅ | ✅ | | | | |
| **Estimate Sent** | Communication fact | ✅ | ✅ | | | ✅ | |
| **Estimate Viewed** | Communication fact | ✅ | ✅ | | | ✅ | |
| **Estimate Approved** | Communication fact | ✅ | ✅ | | | ✅ | |
| **Estimate Deferred** | Communication fact | ✅ | ✅ | | | | |
| **Appointment Scheduled** | Appointment | ✅ | | | | ✅ | |
| **Customer Arrived** | Appointment | ✅ | ✅ | ✅ | | ✅ | |
| **Appointment No-Show** | Appointment | ✅ | | | | ✅ | |
| **Vehicle Checked In** | Vehicle | ✅ | ✅ | ✅ | | ✅ | |
| **VIN Verified** | Vehicle | | ✅ | ✅ | | | |
| **Extension Registered** | Operator Identity | | | | ✅ | | ✅ |
| **Device Attached** | Operator Identity | | | | ✅ | | ✅ |
| **Call Moved** | Operator Identity | | | | ✅ | ✅ | |
| **Presence Changed** | Presence | | | | ✅ | ✅ | |
| **On Call** | Presence | | | | ✅ | ✅ | |
| **Available** | Presence | | | | ✅ | ✅ | |

---

## Projection filters (unchanged — derived)

| Projection | Filter |
|------------|--------|
| **Shop Feed** | Shop = ✅ · since cursor · active (includes **Waiting**) |
| **Recovery Queue** | action ∈ {Action Required, Blocking} · domain ∈ {Message, Call, Communication fact (transport)} · active |
| **Customer Timeline** | Customer = ✅ · customer anchor |
| **RO Timeline** | RO = ✅ · repair_order anchor |
| **Vehicle Timeline** | Vehicle = ✅ · vehicle anchor |
| **Operator Feed** | Operator = ✅ · operator anchor |
| **Customers Browse** | Customer authority + latest Customer-stream head — not a membership column |

---

## Advisor vs technician (projection visibility)

| Projection | Advisor | Technician |
|------------|---------|------------|
| Shop Feed | Full | Minimal |
| Customer Stream | Primary | Via RO only |
| Vehicle Stream | Secondary | Primary |
| Customers Browse | ✅ | ❌ never |

---

## E0b gate

Disagreement on any row → resolve in **event-contracts-v1.md**, not here. This table applies rules already signed.

**Next:** **E1 Contract Realization** — vertical slice per companion-critical verb. Architecture closed.
