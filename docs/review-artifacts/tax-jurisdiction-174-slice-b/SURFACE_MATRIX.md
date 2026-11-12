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
| Mobile RO workspace | Frozen RO projections | **PASS*** | `MobileEstimateProjection` — same calculator path; no live settings |
| Saved estimates / revisions | Snapshot at save | **PASS** | Stamp overlay for tax labels/rates; `TaxJurisdictionSliceBTest` stamp-freeze |
| Print / PDF estimate | Snapshot / living rebuild from stamp | **PASS** | PDF resolve cents match builder in E2E test; `Money::ofMinor` |
| Estimate email body / PDF / links | Snapshot for body total when PDF attached | **PASS** | `EstimateDocumentEmailDelivery` now prefers document `totals.total_cents`; platform mail asserted in E2E |
| Portal estimate + approval | Portal projection + Approved | **PASS** | Portal shows builder total string in E2E |
| Historical approvals | ApprovalEvent + scopes | **PASS** | Amounts include `tax_cents` |
| Invoice create/detail/print/PDF | Invoice snapshot + Approved | **PASS** | Embeds `tax_profile`; stamp overlay; pre-supplemental invoice held to approved cents |
| Invoice email + portal invoice | Invoice snapshot / balance due | **PASS*** | Invoice email `balance_due` from ledger; PDF from frozen invoice snapshot. Portal invoice-pay not re-exercised in Slice B E2E (DEFERRED depth) |
| Dashboards / summaries / reports | Mix of approved / line cents | **DEFERRED** | Do not claim tax feature complete for reporting until Slice C |
| Tax jurisdiction card on RO | Frozen RO stamp after confirm | **PASS** | Affirmative `confirm_jurisdiction` required (`accepted`); checkbox UI |
| Post-auth jurisdiction increase | Living + Approved ceiling | **PASS** | Living tax rises; approved held; UI “Needs authorization”; supplemental raises ceiling; invoice cannot collect unapproved |
| Itemized tax components on docs | Presentation preference only | **DEFERRED (Slice C)** | Preference stored; rendering still combined — safe to leave disabled/combined in initial release |
| Customer tax exemption ≠ Billing Class | — | **DEFERRED (Slice C)** | Safe deferred: Billing Classes continue to work unchanged |
| SaaS automated jurisdiction lookup | — | **OUT OF SCOPE (SaaS)** | |

\*PASS with architecture + focused proof; full portal-invoice / async-job leakage matrix remains thinner than builder→estimate→PDF→email→portal→invoice path covered in `pricing release e2e`.

## Implemented now vs deferred tests

**Now:** schema-2 stamp, matcher ambiguity, affirmative confirmation, manual selection + recalc, catalog immutability, post-auth approved-dollar ceiling + supplemental + invoice, counties persistence, penny snapshot vs stored cents, stamp-freeze labels, pricing-release E2E cents (builder/saved estimate/PDF/email body/portal/invoice/PDF).

**Deferred:** portal invoice-pay cent assertion; full async queued-mail regeneration isolation; itemized component lines; effective-date boundaries; multi-base component taxability; independent customer tax exemption evidence.

## Deferred features — production safety

These can remain **disabled / unused** in an initial production release without breaking shops:

- Itemized component tax presentation (shops keep combined).
- Per-component taxable-base divergence beyond profile-level labor/parts/fees flags.
- Customer tax exemption evidence independent of Billing Classes.
- SaaS automated jurisdiction lookup.

Do not market component-level tax as complete until Slice C lands.
