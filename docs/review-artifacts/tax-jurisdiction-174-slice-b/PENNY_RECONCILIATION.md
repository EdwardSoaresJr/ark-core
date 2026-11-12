# Sales Tax Penny Reconciliation

## Reproduction result

- Synthetic multi-line parts RO at 6.020% with line subtotals 3333/3333/3334 cents.
- Authoritative `EstimateTotalsCalculator` tax cents == sum of line `tax_cents`.
- Estimate snapshot `totals.tax_cents` matches that sum.
- Displayed snapshot tax string matches `number_format(tax_cents/100, 2)` from stored cents.
- **No $0.01 drift found** between calculator, line sum, and snapshot display for this fixture.

Molly’s historical report was **not reproduced** as a stored-cent defect in this pass.

## Likely historical causes (not confirmed as current bugs)

1. **Per-line HALF_UP vs tax-on-subtotal** — policy can differ by ±1¢ from a hand-total of rounded dollars; documents must show stored cents.
2. **Independent formatting paths** — `EstimateSnapshotBuilder::formatCents` uses `number_format($cents/100, 2)`; `InvoiceSnapshotBuilder` uses `Money::ofMinor`. Both are cent-exact for integer cents; float intermediates elsewhere remain a risk.
3. **Recomputing tax from displayed dollars** in a template/JS path — would create penny drift; audit continues under SURFACE_MATRIX deferred E2E automation.

## Affected surfaces reviewed

- Calculator / RO lines: PASS (stored cents)
- Estimate snapshot: PASS (this test)
- Invoice / PDF / email / portal: not fully re-proven here — DEFERRED dedicated matrix (see SURFACE_MATRIX.md)

## Fix / tests

- Added regression: `TaxJurisdictionSliceBTest::sales tax penny reconciliation keeps stored cents equal to displayed snapshot cents`
- **No rounding-policy change** and **no historical rewrite**.

## Remaining uncertainty

- Cannot assert Molly’s original RO without that fixture. Next slice should add a builder→PDF→portal→invoice cent equality suite on schema-2 stamps.
