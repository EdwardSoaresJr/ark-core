# End-to-end financial surface matrix (Slice B gate)

Authority legend:
- **Frozen RO** = `tax_profile` / commercial / shop-fee stamps on the RO
- **Snapshot** = saved estimate / invoice `snapshot_json`
- **Approved** = approved-amount ceiling + `ApprovedWorkScope` amounts
- **Live settings** = only for new ROs or explicitly unstamped living ROs

| Surface | Pricing authority | Status | Notes / paths |
|---|---|---|---|
| RO builder / line editor / concern totals | Frozen RO via `EstimateTotalsCalculator` | **PASS** | `EstimateTotalsCalculator`, concern line blades |
| Estimate totals rail | Frozen RO | **PASS** | `x-operations.estimate-totals-panel` |
| Mobile RO workspace | Frozen RO projections | **PASS*** | `MobileEstimateProjection` — verify same cents; no live settings |
| Saved estimates / revisions | Snapshot at save | **PASS** | `EstimateSnapshotBuilder` stores `tax_cents`; tax labels/rates from RO stamp overlay (fixed in Slice B follow-up) |
| Print / PDF estimate | Snapshot | **PASS*** | Must not recompute tax from dollars; formatters aligned to `Money::ofMinor` |
| Estimate email body / PDF / links | Snapshot / queued document | **PASS*** | Async jobs must load document snapshot, not live settings — covered by existing document jobs; dedicated leakage tests still thin |
| Portal estimate + approval | Snapshot + Approved | **PASS*** | Portal reads document/approval authority |
| Historical approvals | ApprovalEvent + scopes | **PASS** | Amounts include `tax_cents` |
| Invoice create/detail/print/PDF | Invoice snapshot + Approved | **PASS** | Embeds `tax_profile`; tax metadata from stamp overlay |
| Invoice email + portal invoice | Invoice snapshot | **PASS*** | Same snapshot rule |
| Dashboards / summaries / reports | Mix of approved / line cents | **DEFERRED** audit depth | Do not claim tax feature complete for reporting until Slice C itemized + exemption work |
| Tax jurisdiction card on RO | Frozen RO stamp after confirm | **PASS (new)** | `tax-jurisdiction-card.blade.php`, `SelectTaxProfileOnRepairOrderAction` |
| Itemized tax components on docs | Presentation preference only | **DEFERRED (Slice C)** | Preference stored; rendering still combined |
| Customer tax exemption ≠ Billing Class | — | **DEFERRED (Slice C)** | |
| SaaS automated jurisdiction lookup | — | **OUT OF SCOPE (SaaS)** | |

\*PASS with existing architecture; Slice B does not re-prove every PDF/email path in this PR. Block “full E2E tax complete” until dedicated cross-surface cent tests land for email/portal/PDF on schema-2 stamps.

## Implemented now vs deferred tests

**Now:** schema-2 stamp, matcher ambiguity, manual selection + recalc, catalog immutability, post-auth approved-dollar ceiling, counties persistence, penny snapshot vs stored cents, estimate/invoice stamp-freeze for tax labels (not live catalog).

**Deferred:** full builder→email→portal→invoice cent matrix automation; itemized component lines; effective-date boundaries; multi-base component taxability; independent customer tax exemption evidence.
