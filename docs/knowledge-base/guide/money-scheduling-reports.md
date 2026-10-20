---
title: Money, scheduling, and reports
description: The ledger, appointments, and reports that ship in ARK Core.
---

# Money, scheduling, and reports

<!-- source: app/Providers/AppServiceProvider.php -->

## Payments the shop already took

Core keeps a ledger of payments recorded by the shop: cash, check, or a card charged on a terminal that is not ARK. Deposits, refunds, and voids exist on the repair-order financial workflow and on the mobile repair-order routes that wrap them.

`financial.view` and `financial.manage` are separate capabilities. Recording money is not the same as charging a card inside ARK. In-app card capture is ARK Shop. The presence of a Square-related settings screen does not make Core a Square integration you can turn on with a key from this documentation.

Totals use server-side money handling (`brick/money` is a Composer dependency). Do not reimplement tax in a client and write the result back as the shop total.

## Scheduling

Appointments, including bays, are a Core workflow when the appointments surface is enabled. The scheduler command `appointments:send-reminders` runs every five minutes only when `OperationsFeatures::appointmentsEnabled()` is true. A reminder command is not proof that an SMS left the building.

The mobile API can list the schedule, create an appointment, and patch appointment status for a signed-in staff user. Those routes are in `routes/api.php`.

## Time clock

Technician time clock, including lunch and automatic day handling in the schema, is a Core workflow. `time-clock:sync-auto` runs every five minutes from the scheduler. It syncs clock state. It is not payroll filing.

## Reports

Stock Core includes operational reporting: end of day, sales and payments, margin, owner profit and loss, and production. Report routes sit behind scoreboard capabilities such as `scoreboard.operational.view` and `scoreboard.financial.view`. An advisor has those view capabilities in the default map. A technician does not.

There is no public report-builder API. Adding a report is a code change. See [proposed extensions](/tutorials/proposed).

## Limitations

- The ledger is the shop's record of money it already handled. It is not a merchant account.
- Owner digest email (`shop-excellence:owner-digest`) sends only when that digest is enabled, and only if mail is configured to leave the server.
