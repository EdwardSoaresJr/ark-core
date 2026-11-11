# Closeout / immutability (RO #20)

## Paid closeout while unpaid — Pass (rejected)

- Attempt: status → `closed:paid` while `payment_status=unpaid`, invoice balance $259.50 due.
- UI copy: “Collect the full balance due before closing this repair order.”
- Persisted status remained **`ready_pickup`** / **`unpaid`** (DB verified).
- Full immutable closeout after successful payment: **Blocked** (depends on Square payment).
- Post-close financial modification attempt: **Not Run** (RO not closed).

## Final state at stop

| Field | Value |
| --- | --- |
| status | ready_pickup |
| payment_status | unpaid |
| Invoice #7 | issued $259.50 |
| Ledger payments | none |
| Auth ceiling | $259.50 |
| Unauthorized on estimate | +$108.00 |
