# Sales Tax Penny Reconciliation

## Reproduction result

- Synthetic multi-line parts RO at 6.020% with line subtotals 3333/3333/3334 cents.
- Authoritative `EstimateTotalsCalculator` tax cents == sum of line `tax_cents`.
- Estimate snapshot `totals.tax_cents` matches that sum.
- Displayed snapshot tax string matches formatting of stored cents (not a recomputed rate).
- **No $0.01 drift found** between calculator, line sum, and snapshot display for this fixture.

Molly’s historical report was **not reproduced** as a stored-cent defect in this pass.

## Likely historical causes (not confirmed as current bugs)

1. **Per-line HALF_UP vs tax-on-subtotal** — policy can differ by ±1¢ from a hand-total of rounded dollars; documents must show stored cents.
2. **Independent formatting paths** — mitigated in this follow-up: estimate/invoice snapshot `formatCents` and `EstimateTotals::format` all use `Money::ofMinor`. Remaining risk: Blade/JS paths that recompute from dollars (see SURFACE_MATRIX deferred E2E).
3. **Live catalog labels on frozen documents** — fixed: estimate/invoice snapshots overlay tax label/rate/taxability from the RO stamp via `settingsWithTaxOverlay`.

## Affected surfaces reviewed

- Calculator / RO lines: PASS (stored cents)
- Estimate snapshot: PASS (penny + stamp-freeze tests)
- Invoice snapshot metadata: PASS (embeds `tax_profile`; stamp overlay for labels)
- Invoice / PDF / email / portal full cent matrix: DEFERRED (see SURFACE_MATRIX.md)

## Fix / tests

- Regression: `sales tax penny reconciliation keeps stored cents equal to displayed snapshot cents`
- Regression: `estimate and invoice snapshots freeze tax labels from the RO stamp not live settings`
- Aligned `EstimateSnapshotBuilder::formatCents` to `Money::ofMinor`
- **No rounding-policy change** and **no historical rewrite**.

## Remaining uncertainty

- Cannot assert Molly’s original RO without that fixture. Next slice should add a builder→PDF→portal→invoice cent equality suite on schema-2 stamps.
