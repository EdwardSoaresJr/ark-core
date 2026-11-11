# Invoice reconciliation (RO #20)

## Lifecycle to invoice — Pass

Status path (UI): `estimate` → `waiting_approval` → `approved` → assign Taylor Tech → set mileage in/out → `in_progress` → `ready_pickup`.

Moving to **Ready for Pickup** auto-issued final invoice (document #7).

Banner: “RO moved to Ready for Pickup. Final invoice issued.”

## Invoice #7 snapshot — Pass

| Field | Expected | Actual |
| --- | --- | --- |
| document_type | invoice | invoice |
| status | issued | issued |
| Labor | $165.00 | $165.00 |
| Parts | $94.50 (approved amount, not live $120) | $94.50 |
| Fees | $0.00 | $0.00 |
| Tax | $0.00 | $0.00 |
| **Total** | **$259.50** | **$259.50** |

Unauthorized amounts **excluded**:

- Post-auth labor $82.50 — not invoiced
- Part increase +$25.50 — not invoiced (invoice counts approved $94.50)
- Declined Testing Package $0 — not invoiced

Estimate worksheet still shows customer total **$367.50** (conversation prep). Invoice authority is **$259.50**. Settlement balance **$259.50**. Ledger entries: **0** (unpaid).

Screenshot: `screenshots/03-invoice-issued-25950.png`
