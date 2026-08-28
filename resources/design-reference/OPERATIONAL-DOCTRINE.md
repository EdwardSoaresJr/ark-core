# ARK Operational Reference Doctrine

These references exist to ground ARK in real repair-shop operations software.

## Current Phase

ARK is in the Core Operational Foundation Phase.

Current UX study is limited to core daily shop operations:

- Queue.
- Dashboard.
- Repair orders.
- Estimate workflow.
- Approvals.
- Customer workflow.
- Vehicle workflow.
- Technician workflow.
- Parts and procurement basics.
- Invoicing.
- Financial authority.
- Reporting and KPIs.
- Operational pressure visibility.

Do not study, reference, design for, or implement:

- Plugins.
- Addons.
- App marketplaces.
- Integration ecosystems.
- Bolt-on modules.
- White-label extensions.
- Custom widget systems.
- Advanced multi-shop enterprise tooling.
- Optional feature packs.
- Peripheral ecosystem products.
- AI marketing pages.
- Niche third-party integrations.

If a reference is not part of the shop's core daily operational rhythm, it is out of scope for current UX study.

## Adaptation Rule

ARK should aggressively adopt proven operational patterns from Tekmetric and AutoLeap unless ARK already has a clearly superior operational behavior.

Originality is not the goal. Operational familiarity is.

## What Mature Shop Systems Prioritize

- Active queue pressure.
- Approval state.
- Customer communication.
- Estimate readiness.
- Declined and deferred work.
- Parts availability.
- Technician assignment.
- Work progress.
- Payment and posting state.
- Financial truth.
- Production efficiency.
- Customer and vehicle history.

## Queue Doctrine

Queue surfaces should answer:

- What is waiting on approval?
- What is waiting on parts?
- What is waiting on customer?
- What is ready for work?
- What is in progress?
- What is complete but unpaid?
- What is paid but not posted?
- Who owns the next move?

Use operational statuses, not generic CRUD statuses.

## Estimate Doctrine

Estimate surfaces should:

- Start from customer and technician concerns.
- Group work into jobs.
- Keep labor, parts, sublets, notes, and totals together.
- Keep approval posture visible.
- Preserve declined/deferred work.
- Attach DVI/photo evidence where useful.
- Transition cleanly into production.

An estimate is workflow control, not just a printable quote.

## Approval Doctrine

Approval surfaces should:

- Show what was presented.
- Show what was approved, declined, deferred, or reopened.
- Preserve point-in-time authorization history.
- Keep customer communication near the estimate.
- Drive the transition into Work-In-Progress.

Approval is an operational gate.

## Customer And Vehicle Doctrine

Customer and vehicle context should:

- Follow the advisor through intake, RO review, estimate building, and approval.
- Surface service history, declined work, deferred work, active work, and communication.
- Support multi-vehicle and fleet grouping without becoming a CRM island.
- Keep vehicle identity, VIN, mileage, history, and inspection context visible.

## Production Doctrine

Production systems should:

- Assign work clearly.
- Track technician ownership.
- Track job clock or time where relevant.
- Separate live workflow reporting from pay/efficiency reporting.
- Surface blocked work before it becomes hidden shop drag.
- Keep advisor and technician views synchronized.

## Reporting Doctrine

Reports and KPI surfaces should answer real management questions:

- What is stalled?
- What is unpaid?
- What is producing GP?
- Where is workflow pressure?
- Which tech is overloaded?
- What approvals are aging?
- What parts are delayed?
- Which advisors close fastest?
- What work was declined or deferred?

Prefer:

- Server-authoritative data.
- Calm summary hierarchy.
- Dense, auditable tables.
- Clear filters.
- Financial authority.
- Workflow-centered drill-downs.

Avoid:

- Dashboard theater.
- Vanity metrics.
- Decorative chart walls.
- Generic SaaS analytics language.
- BI-clone complexity.

## Visual Doctrine

Operational UI should be:

- Dense but readable.
- Calm under pressure.
- Review-first.
- Workflow-driven.
- Service-lane oriented.
- Financially authoritative.

Charts can clarify trends. Tables carry financial truth. Status labels carry workflow truth.
