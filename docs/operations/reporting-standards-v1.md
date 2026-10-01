# Shop KPI dictionary

**Status:** End of Day, cash, and the operational report use these formulas. The Shop Dashboard still shows open work. Production is unchanged until this is approved.  
**Date:** 2026-09-22

A shop should be able to hand the report to a business coach without translating the labels. These are the familiar names. Where coaching programs disagree, the report says which version it is.

Sales reports use repair orders **posted** in the period. Historical sales come from the posted invoice, not from lines that can still be edited. Tax is not sales. A discount reduces sales when the invoice recorded it. A write-off is shown beside the invoice. It does not, by itself, remove the invoice from sales. Work still marked Recommended, Declined, or Deferred is not on the invoice. RO #1699 is the check for that: its Recommended $391.61 is not sales.

Invoices and ledger entries stay as written. A wrong report is fixed in the report, not by rewriting the ticket.

Bookend and Cecil Bullard can be added later as named frameworks, with their own formulas written next to this list. They do not rename these figures.

## Completed-sales figures

| Name | Formula | Period | Source |
| --- | --- | --- | --- |
| Car count | Number of repair orders | Posted in the period | Posted repair orders |
| Posted invoice sales | Frozen invoice, before tax | Posted in the period | `subtotal_before_tax_cents` when the invoice stored it. Otherwise invoice total minus tax. Not today's repair-order lines. |
| Invoice total | Posted invoice sales + tax | Same invoices | Frozen invoice total |
| ARO | Posted invoice sales ÷ car count | Same posted repair orders | Same sales and car count |
| Write-offs | Sum of write-off transactions | The date the write-off was recorded | Ledger write-offs. Not cash, and not a silent reduction of posted invoice sales. |
| Labor sales | Labor dollars | Posted in the period | Approved labor lines, before tax. Incomplete as a margin when those lines no longer match the invoice. |
| Parts sales | Parts dollars | Posted in the period | Approved part lines, before tax. Same matching rule as labor. |
| Parts gross profit | Parts sales − parts cost | Posted in the period | Parts cost is unit cost × quantity. Incomplete data when a part has no cost, or the lines no longer match the invoice. |
| Gross profit | Posted invoice sales − matched cost | Posted in the period | Parts cost and loaded labor cost, only when every posted repair order's current lines still foot to its invoice and every cost is present. Otherwise Incomplete data. |
| Gross profit margin | Gross profit ÷ posted invoice sales | Same sales as gross profit | Percent. Tax is not in the sales. |
| Effective labor rate | Labor sales ÷ billed hours | Posted in the period | Billed hours on those labor lines. The door rate is a separate number. |
| Cash collected | Receipts − refunds | Payment date | Payments and deposits, minus refunds. Voided entries omitted. Write-offs are not cash. |

Labor cost for gross profit is the technician’s loaded rate × the billed hours on the labor being measured.

The invoice total is posted invoice sales plus tax. That total is for reconciliation. It is not posted invoice sales, and it is not ARO. A courtesy, trade, or goodwill invoice stays at the billed amount. The write-off is the separate record of what was not collected.

## Hours

| Hours | Meaning |
| --- | --- |
| Billed hours | Hours charged to the customer on the job |
| Available hours | Hours the technician was available to work |
| Actual hours | Hours spent on the job |

| Name | Formula | Period | Source |
| --- | --- | --- | --- |
| Labor productivity | Billed hours ÷ available hours | The report range | Billed hours on repair orders closed in the range, divided by shop open days × the technician’s workday |
| Labor efficiency | Billed hours ÷ actual hours | The same range | Time on the job and billed hours on posted work |

Some coaches swap these two names. The report always prints the hours in the label: billed ÷ available, or billed ÷ actual. A named coaching framework can switch the words later. It cannot hide which hours were used.

If the hours in the formula were not recorded, the figure says Incomplete data. It does not show a percentage.

## Closing ratio

There is no single closing ratio in the trade. Every closing ratio on a report names its denominator and says whether work still waiting is included. Two different formulas are never shown under one unqualified “Closing ratio” label.

| Name | Formula | Period | Source |
| --- | --- | --- | --- |
| Closing ratio (hours) | Billed hours ÷ labor hours presented | One population and one period for both | Hours presented are the labor hours put in front of the customer |
| Closing ratio (dollars) | Approved dollars ÷ dollars presented | One population and one period for both | Dollars presented are approved + declined + still waiting, before tax |

Some programs drop work that is still waiting and divide approved dollars by approved plus declined only. That is closing ratio (dollars) on decided work. The label has to say so.

A count of repair orders that have any approval is not a closing ratio.

## Where programs already disagree

| Topic | What differs |
| --- | --- |
| Car count | Posted, invoiced, closed, or opened. Sales ARO uses posted, matching Sales. |
| ARO sales | Tax in or out, and whether fees sit inside sales. Here, tax is out and fees are in. |
| Closing ratio | Hours, dollars, or dollars on decided work only. |
| Effective labor rate | Labor sales ÷ billed hours is the usual meaning. Labor sales ÷ clock hours is a different rate. |
| Productivity and efficiency | Which name means billed ÷ available, and which means billed ÷ actual. |

## Open work

The Shop Dashboard and Job Board show work still in the shop. Those figures use everyday words. They are not the KPIs above.

| On the board | What it is |
| --- | --- |
| Cars in the shop | Open repair orders right now |
| Estimates to finish | Open repair orders still being written |
| Awaiting approval | Dollars still recommended, not yet yes or no |
| Approved, not posted | Approved dollars on repair orders that are still open |
| Amount due | Balance still owed after an invoice |

An average of approved dollars on open repair orders is a workload figure. It is not ARO.

## Incomplete figures

A percentage is shown only when every cost or hour that formula needs is on the ticket, and the current lines still foot to the posted invoice. Otherwise the report says **Incomplete data** and identifies what is missing. A blank cost is not treated as zero. A line edited after posting does not change posted invoice sales. If that edit means the cost no longer matches the invoice, gross profit stays incomplete.

## What stays put

The Shop Dashboard still shows open work. Those labels are not these KPIs. Invoices, ledger entries, and historical repair orders are not rewritten to make a report match. Production is unchanged until this reporting change is tested and explicitly approved.
