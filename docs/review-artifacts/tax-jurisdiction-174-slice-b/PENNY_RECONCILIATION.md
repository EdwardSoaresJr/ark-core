# Sales Tax Penny Reconciliation

## Reproduction result

- Synthetic multi-line parts RO at 6.020% with line subtotals 3333/3333/3334 cents.
- Authoritative `EstimateTotalsCalculator` tax cents == sum of line `tax_cents`.
- Estimate snapshot `totals.tax_cents` matches that sum.
- Displayed snapshot tax string matches `Money::ofMinor` formatting of stored cents.
- Pricing-release E2E: builder, saved estimate, PDF resolve, estimate email `estimate_total`, portal estimate, invoice snapshot, and invoice PDF all share the same total/tax cents for a labor+parts+fee+tax RO.
- **No $0.01 drift found** between calculator, line sum, and customer-facing display for these fixtures.

Molly’s historical report was **not reproduced** as a stored-cent defect in this pass.

## Likely historical causes (not confirmed as current bugs)

1. **Per-line HALF_UP vs tax-on-subtotal** — policy can differ by ±1¢ from a hand-total of rounded dollars; documents must show stored cents.
2. **Independent formatting paths** — estimate/invoice snapshot `formatCents` and `EstimateTotals::format` use `Money::ofMinor`. Remaining risk: Blade/JS paths that recompute from dollars.
3. **Email body vs PDF** — previously `EstimateDocumentEmailDelivery` used live calculator totals for `estimate_total` while attaching a PDF from a document snapshot. Fixed: email body prefers document `totals.total_cents` when a PDF document is attached.
4. **Live catalog labels on frozen documents** — fixed via stamp overlay.

## Affected surfaces reviewed

- Calculator / RO lines: PASS
- Estimate snapshot + PDF resolve: PASS
- Estimate email body: PASS (after fix)
- Portal estimate: PASS (E2E)
- Invoice snapshot + PDF: PASS
- Portal invoice-pay: DEFERRED depth

## Fix / tests

- `sales tax penny reconciliation keeps stored cents equal to displayed snapshot cents`
- `pricing release e2e reconciles authoritative cents across builder estimate pdf email portal and invoice`
- `estimate and invoice snapshots freeze tax labels from the RO stamp not live settings`
- **No rounding-policy change** and **no historical rewrite**.

## Remaining uncertainty

- Cannot assert Molly’s original RO without that fixture.
- Portal invoice-pay and queued async mail regeneration still thinner than the E2E path above.
