# Tekmetric Operational Patterns

## Reporting Posture

Tekmetric separates real-time operational reporting from posted financial reporting.

- Shop Dashboard real-time reporting includes active job board and posted views.
- End of Day report is based on posted ROs and functions as financial truth.
- Real Time Technician report is workflow-oriented.
- Technician Hours report is billed-hours/pay and efficiency oriented.

ARK implication: do not blend active workflow pressure and closed financial truth without clearly naming the source of truth.

## Management KPIs

Recurring management metrics:

- Car Count
- Pending Sales
- Declined Sales
- Approved Sales
- ARO
- AWRO
- Close Ratio
- Hours Written
- Hours Sold
- Effective Labor Rate
- Gross Sales per Hour
- Gross Profit per Hour
- Avg RO Sales
- Avg RO Profit
- Avg RO Profit Margin

These are shop-management metrics, not SaaS vanity analytics.

## Technician Production

Tekmetric technician tracking has two separate meanings:

- Real-time technician reporting shows workflow status, dollars, and hours on active/postable work.
- Technician Hours report shows billed time, actual clocked time, calculated efficiency, job count, and technician drill-downs.

Job clock workflow:

1. Technician opens assigned RO.
2. Technician enters Work-In-Progress tab.
3. Technician starts the job clock on a job card.
4. Clock keeps running until stopped.
5. Completing labor can automatically stop the clock if the technician forgot.

ARK implication: production tracking should distinguish live workflow state from technician pay/efficiency state.

## Customer And Vehicle Rhythm

Job history appears in multiple places:

- Customer profile.
- RO summary tab.
- RO estimate tab.
- Appointment screen.

History includes approved, declined, draft, and saved-for-later jobs. Inspection history can also be copied into a current RO, preserving repeated-service context.

ARK implication: customer and vehicle history should follow the advisor into intake, estimate building, and review. It should not live in a disconnected CRM island.

## Financial Authority

Tekmetric reports make money visible through:

- Labor, parts, sublets, fees.
- Discounts.
- Taxes.
- Payment reconciliation.
- Costs.
- GP dollars.
- GP percent.

ARK implication: financial reporting should be dense, table-forward, and auditable. Charts may summarize, but tables carry authority.
