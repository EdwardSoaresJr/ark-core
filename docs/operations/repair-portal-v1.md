# Repair Portal v1

**Status:** Frozen · Slice 1 implementing  
**Classification:** Foundational Portal capability  
**Companions:** Evidence Authority · EstimateAccessToken (legacy deep links) · Inspection share tokens (migrate later)

## Frozen rule

Every customer document advertises the **Repair Portal**, not individual capabilities.  
The QR is “Open your vehicle online,” not “View photos” or “View estimate.”

## Principle #1

**The Repair Portal is the customer's durable relationship with a Repair Order.**

Documents, SMS, approvals, inspections, evidence, invoices, and future capabilities advertise or consume the Portal. None of them own customer access.

Do not invent InvoicePortal · InspectionPortal · EstimatePortal.

## Principle #2

**Customer access is durable. Features join the Repair Portal; they do not create competing customer entry points.**

Every customer-facing URL should reduce to:

```text
RepairOrderPortalAccess → Repair Portal
```

Estimate / inspection / evidence / invoice tokens become implementation details behind that doorway.

## Append-only access

`public_code` is never mutated.

Compromise → revoke row A (`revoked_at`) → mint row B (new code).  
Same supersession philosophy as MaintenanceServiceEvent.

## Hub (customer home)

Answers: **What is happening with my vehicle?**

Order:

1. Vehicle  
2. Current Status  
3. Estimate (card — “Updated … ago”, not estimate #)  
4. Photos (Shared Evidence)  
5. Inspection (later)  
6. Messages (later)  
7. Portal Notices (later — hub, not SMS)

## Growth

```text
Repair Portal
  Estimate
  Evidence
  Inspection
  Invoices
  Maintenance History
  Warranty
  …
```

## Advertisement

`RepairPortalAdvertisementProjection` — only Portal generates QR URLs.  
Documents consume; they never invent access.

**Frozen consumers (wire over time):** Estimate · Invoice · Authorization · Inspection PDF · Maintenance report · SMS · Email · Oil sticker (maybe).

**Slice 1 consumer:** Estimate PDF (always print QR; dynamic copy).

## Non-goals (Slice 1)

- Printed photo grids  
- Portal Notices UI  
- Retiring EstimateAccessToken / InspectionAccessToken  
- Full My Account rewrite  
