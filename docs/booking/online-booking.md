# Online appointment requests (Core)

Core owns scheduling for online bookings: the shop's rules, the authoritative checks, the appointment request record, and how a request reaches staff on the Schedule. It has no public booking form of its own beyond the website's existing `/book` lead form. A booking product — ARK Shop's Book Appointment, or any other front end — presents services, questions, branding, embedding and customer verification, and submits through `App\Ark\Operations\Booking\OnlineBooking`.

## Ownership

| Core | Booking product (ARK Shop) |
| --- | --- |
| Scheduler, calendar, appointment records and lifecycle | Public Book Appointment page and embeddable popup |
| Availability: request days and windows, closed dates, horizon, notice | Book Service Builder: services, two option stages, durations, order |
| Capacity, daily limit, concurrent appointments, scheduling hours | Branching questions, customer and vehicle intake |
| Request vs confirmed policy, approval downgrade | Branding, background, messages, button labels |
| Service area and tagged constraints, failure handling | Embed script, allowed domains, WordPress plugin, ARK Sites |
| Appointment requests on the Schedule: Pick a time, Decline | Customer verification codes, anti-spam, receipts |
| Lead record, `AppointmentRequestReceived` / `AppointmentRequestDecided` | Submission orchestration through `OnlineBooking` |

Core works without a booking product: staff schedule as before and the website's `/book` keeps recording leads.

## The contract

All calls run inside the shop (tenancy initialized).

- `OnlineBooking::availability(BookingContext $context, ?string $date): array` — `mode` (`request` or `instant`), `dates` (each `available` with a `reason`), `selected_date`, `periods` (request mode) or `slots` (instant mode) for that date, `timezone`, `accepting`, `message`, and `notice` when a required check could not run.
- `OnlineBooking::submit(OnlineBookingRequest $request): BookingOutcome` — repeats every check inside a lock on the shop's settings row, records the lead and an `appointment_requests` row, and books the appointment only when confirmed booking applies and the time is still open. A resubmitted `submissionKey`, or a second pending request for the same phone and day, returns the first request. Throws `BookingRefused` (`field`, customer-readable message) when the day, time or address cannot be taken.
- `OnlineBooking::request(string $uuid): ?AppointmentRequest` — for the product's receipt.

`BookingContext` is what the scheduler needs from the product's catalog: service and option labels, per-vehicle minutes and labor hours, vehicle count, location (`shop`, `customer_site`, `fleet_site`) and address, whether the booking insists on approval, and an opaque `serviceReference`. `OnlineBookingRequest` adds contact, vehicles, concern text, answers (`label`, `answer`), the preferred day with a time of day or exact time, drivability flags, an optional verified customer and vehicle, the ingress context and spam signals. The product validates its own form and the customer's identity before calling; Core trusts nothing about availability.

## Policy (Settings → Online Requests)

`shop_settings.online_booking` (normalized by `BookingSettings`) holds only what Core enforces:

- **Mode.** Requests (default): the customer picks a day and a time of day; nothing is reserved; staff pick the time. Confirmed: open start times inside scheduling hours, at least an hour ahead, within the concurrent limit (default one per schedulable bay) and the day's scheduling target; a booking that insists on approval still waits.
- **Limits.** Most online bookings per day; whether days at the scheduling target are offered (confirmed bookings always respect capacity).
- **Service area.** ZIP codes for on-site work, and the message outside the area.

Request days, windows, horizon, notice and closed dates stay in Appointment Request Availability and its exceptions, so the Schedule's controls and this page are one rule.

## Constraints

`Contracts\BookingAvailabilityConstraint`, tagged `BookingConstraints::TAG`, is asked about every offered day and confirmed start, and again on submission, with the full context. A constraint is **required** unless it implements `AdvisoryBookingConstraint`. When a required one throws, no exact time is offered or confirmed, confirmed booking falls back to a request (keeping the asked-for time as "around 9:00 AM"), staff see "Not checked online: …", and the error is reported once per evaluation. A refusal from a check that did run still wins. `ServiceAreaConstraint` is Core's own.

## Staff

Pending requests appear under **Online requests waiting** on the Schedule. **Pick a time** opens the normal appointment form with the preference, visit length, labor hours, notes and service address; saving it schedules the request and its lead. **Decline** closes the request and marks the lead lost. Rescheduling afterwards is the normal appointment reschedule. The service address is copied to the appointment (`service_location_type`, `service_address`).
