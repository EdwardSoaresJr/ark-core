# Shop KPI dictionary

**Status:** End of Day, cash, and the operational report use these formulas. The Shop Dashboard still shows open work. Production is unchanged until this is approved.  
**Date:** 2026-09-22

A shop should be able to hand the report to a business coach without translating the labels. These are the familiar names. Where coaching programs disagree, the report says which version it is.

Sales reports use repair orders **posted** in the period, and the approved work on those repair orders. Tax is not sales. Discounts reduce sales once. Work still marked Recommended, Declined, or Deferred is not sales. RO #1699 is the check for that: its Recommended $391.61 is not sales.

Invoices and ledger entries stay as written. A wrong report is fixed in the report, not by rewriting the ticket.

Bookend and Cecil Bullard can be added later as named frameworks, with their own formulas written next to this list. They do not rename these figures.

## Completed-sales figures

| Name | Formula | Period | Source |
| --- | --- | --- | --- |
| Car count | Number of repair orders | Posted in the period | Posted repair orders |
| Sales | Labor + parts + sublet + other − discounts | Posted in the period | Approved lines on those repair orders. Other means shop fees and fee lines. |
| ARO | Sales ÷ car count | Same posted repair orders as Sales | Same as Sales and car count |
| Labor sales | Labor dollars | Posted in the period | Approved labor lines, before tax |
| Parts sales | Parts dollars | Posted in the period | Approved part lines, before tax |
| Parts gross profit | Parts sales − parts cost | Posted in the period | Parts cost is unit cost × quantity. If any part has no cost, the margin says Incomplete data and names the sales that are missing a cost. |
| Gross profit | Sales − the cost of those sales | Posted in the period | Parts cost, labor cost, and sublet cost when it is recorded |
| Gross profit margin | Gross profit ÷ sales | Same sales as gross profit | Percent. Tax is not in the sales |
| Effective labor rate | Labor sales ÷ billed hours | Posted in the period | Billed hours on those labor lines. The door rate is a separate number. |
| Cash collected | Receipts − refunds | Payment date | Payments and deposits, minus refunds. Voided entries omitted. Write-offs are not cash. |

Labor cost for gross profit is the technician’s loaded rate × the billed hours on the labor being measured.

The customer total on a posted repair order is sales plus tax. That total is for reconciliation. It is not Sales, and it is not ARO.

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

A percentage is shown only when every cost or hour that formula needs is on the ticket. Otherwise the report says **Incomplete data** and identifies the sales or hours that are missing. A blank cost is not treated as zero.

## What stays put

The Shop Dashboard still shows open work. Those labels are not these KPIs. Invoices, ledger entries, and historical repair orders are not rewritten to make a report match. Production is unchanged until this reporting change is tested and explicitly approved.
