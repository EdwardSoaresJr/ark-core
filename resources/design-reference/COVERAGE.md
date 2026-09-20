# Operational UX Reference Coverage

This report tracks current screenshot coverage for operational UX grounding.

ARK is in the Core Operational Foundation Phase. Coverage should prioritize core daily shop operations only and exclude plugins, addons, app marketplaces, integration ecosystems, AI upsells, optional feature packs, and advanced enterprise tooling unless the material directly documents core queue, RO, estimate, approval, technician, parts, invoicing, reporting, or financial workflow.

The current library is useful, but it does not yet meet the target coverage minimums from public-accessible sources alone. Tekmetric has better public support screenshots. AutoLeap public screenshots are much thinner and are mostly constrained to product galleries, review pages, and demo-gated marketing pages.

## Current Counts

### Tekmetric

- dashboard: 3
- queue: 24
- ro-review: 14
- estimate-builder: 23
- customer: 9
- vehicle: 22
- approval-flow: 6
- mobile: 24
- kpi: 1
- reports: 3
- total: 139

### AutoLeap

- dashboard: 30
- queue: 29
- ro-review: 20
- estimate-builder: 34
- customer: 3
- vehicle: 24
- approval-flow: 5
- mobile: 14
- kpi: 1
- reports: 1
- total: 161

## Workflow Intelligence Documents

### Shared

- `OPERATIONAL-DOCTRINE.md`

### Tekmetric

- `tekmetric/help-center/INDEX.md`
- `tekmetric/training/TRAINING-ENTRYPOINTS.md`
- `tekmetric/workflows/OPERATIONAL-LIFECYCLES.md`
- `tekmetric/operations/OPERATIONAL-PATTERNS.md`

### AutoLeap

- `autoleap/help-center/INDEX.md`
- `autoleap/training/TRAINING-ENTRYPOINTS.md`
- `autoleap/workflows/OPERATIONAL-LIFECYCLES.md`
- `autoleap/operations/OPERATIONAL-PATTERNS.md`

## Weak Coverage Areas

- Tekmetric dashboard coverage remains below the 15-25 target and lacks enough truly busy real-world shop states.
- Tekmetric estimate review coverage is useful, but still below the 20-30 target and needs denser multi-concern estimates.
- Tekmetric customer and vehicle coverage needs more examples of multi-vehicle customers, fleet customers, and long histories.
- AutoLeap customer, approval, mobile, KPI, and reports coverage remain below target because public screenshot access is limited.
- AutoLeap customer workflow, approval posture, mobile, KPI, and reporting coverage especially need manual screenshots from demos, training videos, or an authenticated sample account.
- KPI and reporting coverage is very thin for both platforms because public report screenshots are mostly hidden behind support articles, demos, or authenticated accounts.
- Tekmetric reporting doctrine is well documented in public support text, but screenshot coverage is limited to commission/reporting examples plus one real-time dashboard image.
- AutoLeap reporting public sources describe profitability, technician productivity, and dashboard posture, but expose very few direct report screenshots.
- Messy real-world operational states remain under-covered. Public marketing/support screenshots are mostly clean examples.

## Strongest Operational Patterns Found

- Work boards use lane-based operational grouping, not generic tables.
- Status labels matter as much as the primary workflow columns.
- Estimate workflows stay close to approvals, totals, and customer communication.
- Job history is repeatedly surfaced near customer, vehicle, RO, and estimate contexts.
- Vehicle identity and history are operational anchors, not passive metadata.
- Mobile workflows emphasize fast inspection, media capture, and RO lookup from the service lane.
- Approval state is treated as a workflow transition, not just a document signature.
- KPI surfaces tie metrics to active work: pending approvals, declined dollars, approved sales, car count, ARO, close ratio, and labor hours.
- Reporting surfaces separate real-time workflow pressure from posted financial truth.
- Management reporting emphasizes filters by date range, service writer, technician, and job category.
- Financial visibility is anchored in labor, parts, sublets, fees, discounts, costs, GP dollars, and GP percent.

## Recurring Hierarchy And Density Conventions

- Top-level queue screens favor quick scanability: columns, labels, status chips, assigned work, and payment/approval cues.
- RO and estimate screens group work by jobs/concerns, then lines, then financial totals.
- Customer and vehicle records emphasize history access and repeated-service context.
- Operational screens use dense rows and compact controls, but keep status and next action visually distinct.
- Financial hierarchy tends to keep subtotal, package price, labor/parts split, approvals, and payment states close together.
- Customer-facing approval screens simplify the same estimate structure rather than inventing a separate workflow.
- KPI dashboards should present operational questions before charts: what is pending, what is declined, what is approved, what is unpaid, and what hours are in motion.
- Reports use dense table structure for authority, with summaries above and drill-down/export affordances near the data.
- Technician management commonly compares billed time, actual time, efficiency, car count, and assigned status.
- GP and profitability reporting is line-of-business oriented: labor, parts, sublets, fees, discounts, and costs stay visible together.
- Chart restraint matters: charts summarize workflow pressure, while tables carry financial authority.

## Capture Still Needed

To meet the original minimums, collect manual screenshots from real demos, training videos, or an authenticated sample account:

- 15-20 more Tekmetric dashboard examples.
- 10-15 more Tekmetric dense estimate review examples.
- 10-15 more Tekmetric customer/vehicle examples.
- 10-15 more Tekmetric approval examples.
- 20+ Tekmetric KPI dashboard screenshots.
- 25+ Tekmetric reporting screenshots, especially End of Day, Profit Details, Technician Hours, Real Time Technician, Job Categories, Sales Tax, A/P, A/R, and declined work reports.
- 10-20 more AutoLeap customer, approval, and mobile screenshots.
- 20+ AutoLeap KPI dashboard screenshots.
- 25+ AutoLeap reporting screenshots, especially profitability, technician productivity, labor/parts margin, payment summaries, and dashboard filters.
- 20+ messy real-world screenshots across both platforms.

## ARK KPI And Reporting Doctrine

- KPIs must answer real management questions: what is stalled, unpaid, pending approval, declined, overloaded, delayed, or producing gross profit.
- Reports should feel financially authoritative and server-grounded, not decorative.
- Prefer calm summary rows, dense tables, and obvious filters over chart-heavy dashboards.
- Charts are allowed when they clarify workflow pressure or trends, but they should not become dashboard theater.
- Reporting hierarchy should start with actionable totals, then segment by advisor, technician, job category, labor, parts, sublets, payments, and GP.
- Management surfaces should support coaching, staffing, approvals, parts follow-up, and cash collection.
- Avoid generic SaaS analytics language; use shop language and repair-order workflow concepts.

## Usage Rule

Before refining ARK operational surfaces, study the relevant folders first. Default toward operational familiarity, believable service-lane rhythm, workflow scanability, and dense-but-calm hierarchy.
