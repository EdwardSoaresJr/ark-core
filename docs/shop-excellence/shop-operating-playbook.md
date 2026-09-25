# Shop operating playbook

How a shop stays profitable without depending on one person to remember every job.

The playbook is the same for every ARK shop. Targets, labor rate, margin floor, and follow-up cadence belong to that shop's settings. ARK measures the shop against its own configuration.

## The machine

Qualified opportunity, assessment, evidence, recommendation, estimate, presentation, follow-up, authorization, production, quality check, payment, vehicle leaves, retention.

Every repair order needs a next action, a person responsible for it, and a time when that action should happen. An open repair order that cannot answer those questions is stalled. Stalled is a condition ARK derives from status and age. It is not a separate status.

## What the scoreboard watches

Five primary measures. Each one answers a different operating question.

| Measure | Question | Formula |
| --- | --- | --- |
| ROs / open day | Are enough legitimate opportunities entering? | Repair orders opened in the period, divided by days the shop is open |
| Dollar close | Are customers authorizing the work we put in front of them? | Approved pre-tax dollars divided by customer-facing pre-tax dollars, on repair orders opened in the period |
| Sold hours / open day | Are we producing enough billed work? | Approved billed labor hours on repair orders posted in the period, divided by open days |
| Median cycle | Is work moving through the building? | Median of opened-to-posted days for repair orders posted in the period. Average is shown second because one long job distorts it |
| Parts margin | Are parts producing margin? | Posted parts gross profit divided by posted parts sales, and only when every parts line has a cost |

Open days follow the shop's weekly hours and closed dates. A closed Sunday is not an open day.

Dollar close uses current concern disposition. Draft is excluded. Recommended, approved, deferred, and declined are customer-facing: ARK shows them on the estimate, the portal, and printed copies. Undecided recommended dollars stay in the denominator, so a fresh estimate lowers the rate until the customer decides. A sent estimate link is extra evidence of contact. It is not required for the rate, because work can be reviewed in person.

Posted car count, posted sales, payments, and amount due stay on the existing sales and ledger reports. The scoreboard reads them. It does not invent a second set of books.

Authorized backlog is approved dollars on open repair orders. Not authorized is draft, recommended, and deferred dollars on open repair orders. Declined work is not backlog. A full lot is not the same thing as sold work.

Period rates respect the reporting floor. Open counts, backlog dollars, aging, and queues include every repair order that is still open, including one opened before that floor.

## Shop configuration

Settings, Owner Targets, Operating scoreboard:

- ROs / open day
- Dollar close percent
- Sold hours / open day
- Median cycle days
- Stall age in days. Blank uses the median cycle target. If both are blank, the stalled queue stays off.
- Whether parts margin should be judged against the existing parts margin target

Leave a field blank until the shop chooses a number. The scoreboard still shows the actual result. It does not mark a metric good or behind when the shop has not set a target. Parts margin stays display-only until the shop turns that judgment on. The parts margin percent used by Margin Health is a separate setting and is not a scoreboard verdict by itself.

## Attention

The scoreboard lists work that needs a person. Labels stay inside what ARK can prove.

- Presentation / decision needed: draft or estimate, with dollars, and no estimate link on file. That does not prove the estimate was never discussed in person.
- Follow-up: recommended dollars, or the repair order is waiting on approval. Last contact appears only when a communication event is stored on that repair order.
- Aging authorized work: approved work older than the shop's cycle target, or older than five days when no target is set.
- Ready for pickup: includes a balance due when an invoice exists.
- Stalled: open at least as long as the shop's stall age, or its median cycle target when no stall age is set, and not in production, waiting on parts, quality check, or pickup. No number is assumed when the shop has set neither.

## What this is not yet

The scoreboard measures. It does not coach, and it does not add new blocks.

ARK already stops a paid close while a balance is due, and it already stops completion of work outside the approved scope. Later, a shop can connect a weak measure to an explanation, a suggested next step, and a gate only where the process has to hold. That sequence is:

Playbook, shop configuration, workflow, measurement, detection, explanation, suggested action, facilitated action, gates where integrity requires them.

This document and the scoreboard are the measurement foundation. Hiring rules, automated prompts, and a conversational coach are not part of this surface.

## Roles

Five capabilities, granted to roles:

- View operational KPIs
- View financial KPIs
- View queues
- Work the follow-up list
- Open customer and repair-order drilldowns

Admin and advisor receive all five, because the advisor role already sees shop financials. A later shop can remove the financial capability from advisor and still leave queues and follow-up in place. Technician does not receive the scoreboard.

The wall display (`?display=wall`) is the same signed-in page without navigation chrome and without customer names, phone numbers, or email addresses. It refreshes from the server on a timer.
