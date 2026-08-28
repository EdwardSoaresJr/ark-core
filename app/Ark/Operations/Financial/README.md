# ARK Estimate Financial Authority

**Target doctrine:** [`docs/ARK-FINANCIAL-AUTHORITY-V2.md`](../../../docs/ARK-FINANCIAL-AUTHORITY-V2.md) (**Frozen**).

Estimate · Ledger · Final Invoice. Never two living financial contracts. Suspend living-invoice sync / refresh / early-invoice features until v2 is adopted in product code.

Estimate math has one authoritative path:

`EstimateTotalsCalculator`

## Rounding Policy

Authoritative rounding happens at the estimate line level.

1. Convert the entered unit price to integer cents.
2. Multiply quantity by unit price cents.
3. Round that line result deterministically to integer cents using `HALF_UP`.
4. Persist `repair_order_lines.total_cents`.

Concern subtotals are sums of persisted authoritative line cents per concern.

Estimate subtotals and estimate totals sum only billable concerns. Deferred and declined work never count. Recommended work counts only while no approved work exists on the repair order; once any concern is approved, the authoritative total is approved work only.

## Rules

- Store integer cents only.
- Use Brick Math inside `EstimateTotalsCalculator` for decimal arithmetic and rounding.
- Do not recalculate line totals in Blade.
- Do not aggregate decimal money and round later.
- Do not let Alpine or browser code calculate money.
- Do not put estimate total aggregation in Eloquent models.
- Use Blade only for formatting already-authoritative cents.
- Fees must be real estimate lines. Do not hide shop fees inside labor or part line math.

## Immutable Snapshot Rule

**Issue Final Invoice** persists an immutable financial snapshot. That snapshot is historical evidence — not a living contract — and must not be recalculated from current shop settings, pricing matrix, fee rules, or tax rules. Do not “refresh” it to chase living estimate changes.

## Financial Core (Authority Layer)

| Calculator / Action | Role |
|---------------------|------|
| `EstimateTotalsCalculator` | Living estimate only |
| `InvoiceSnapshotBuilder` + `GenerateInvoiceSnapshotAction` | Closeout readiness → immutable Final Invoice |
| `BalanceDueCalculator` | Post-invoice payment/closeout gates (transitional; owe-today display uses Financial Position) |
| `FinancialPositionProjection` | **Customer owes today** — disposable; Estimate + Ledger (+ Coverage later); Invoice if issued |
| `FinancialPositionCalculator` | Sole orchestrator behind Position — GET-pure |
| `RecordLedgerEntryAction` | Deposits, payments, refunds, adjustments, store credit |
| `RepairOrderCloseoutAuthority` | Closeout readiness → Issue Final Invoice → balance 0 → close |

### Transitional — do not extend (v2)

`RefreshLivingInvoiceSnapshotAction`, `RefreshCustomerInvoiceAction`, and early mid-job invoices are **compatibility**, not target architecture. See Financial Authority v2 Implementation freeze.

### Temporary compatibility (remove after financial workflow UI)

`RepairOrderPaymentPostureSync` mirrors ledger balance into `repair_orders.payment_status` / `paid_at` for legacy queue and report surfaces. **Not financial truth.** Do not add new callers; delete once RO review shows calculator-driven payment posture.

See `docs/ARK-FINANCIAL-AUTHORITY-V2.md` (authority) and `docs/ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md` (ledger/closeout reference).
