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
- `OnlineBooking::request(string $uuid): ?AppointmentRequest` — for the product's receipt; `forSubmission(string $key)` — the request a submission key already created, to answer a retry.

### Proof of identity

A product passes its customer's verification as `OnlineBookingRequest::$proof` (`Contracts\BookingProof`). Core spends it inside the booking transaction, under the shop lock, after the submission-key and same-day checks and every refusal, and before recording anything: a spent or expired proof is refused (`BookingRefused('verification', …)`), and a booking that fails afterwards rolls the spend back. Phone and email proofs are consumed with a conditional update that must change exactly one verification row for that contact, so two requests holding copies of the same proof cannot both book. A resubmitted submission key returns the first outcome without spending anything.

`submit` sends nothing: not the website lead confirmation either (`LeadRecorder` is called with `confirm: false`). `AppointmentRequestReceived` fires after commit for new requests only; the product acknowledges the customer. The lead's `metadata.identity` is `verified` when a proof was spent.

Core's own website form (`/leads`) stays separate: its leads carry `metadata.identity` (`verified` or `unverified`); staff see "unverified contact" on unverified ones, and they are linked to a customer only through a signed-in portal customer, never by matching a phone or email.

`BookingContext` is what the scheduler needs from the product's catalog: service and option labels, per-vehicle minutes and labor hours, vehicle count, location (`shop`, `customer_site`, `fleet_site`) and address, whether the booking insists on approval, and an opaque `serviceReference`. `OnlineBookingRequest` adds contact, vehicles, concern text, answers (`label`, `answer`), the preferred day with a time of day or exact time, drivability flags, an optional verified customer and vehicle, the ingress context and spam signals. The product validates its own form and the customer's identity before calling; Core trusts nothing about availability.

### Several things in one visit

`BookingContext::forWork(list<BookingWorkItem>, vehicleCount, location, address)` builds one visit from several items: a catalog service with its options, answers and notes (`service`), a problem in the customer's words (`concern`), or work the shop recommended before (`recommendation`, with its id and vehicle). The shop's **visit length** policy combines them:

- **Drop-off** (default): the visit is as long as its longest item; the vehicle stays while the work is done.
- **One after another** (`sequential`): the items' times add up, for customers who wait or a mobile technician working through the list.

The workload is always the sum of the items' labor hours (an item without hours counts its minutes), multiplied by the vehicle count; capacity uses it. A visit longer than a day (480 minutes) is never confirmed online: it becomes a request. An item without a time counts an hour. `submit` stores the items in order in `appointment_requests.selections`, lists them in the concern and staff notes, and refuses (`BookingRefused`) a saved vehicle or recommendation that does not belong to the request's customer, or a recommendation that is no longer open. Choosing recommended work changes nothing about the recommendation, its estimate or its repair order.

### Recognizing a verified customer

`BookingRecognition::forVerifiedContact(?phone, ?email)` is for products that have already proven the contact with a code. A customer is recognized only when exactly one record in the shop has that phone or email; no match and several matches both disclose nothing. Through a recognized customer only: `vehicles()` (id, year make model trim, plate), `vehicle(id)`, `recommendations(vehicleId)` (open ones made to this customer: title, customer description, urgency, safety, date; never prices, advisor notes or VINs) and `recommendation(vehicleId, id)`. It never creates or merges customers.

### Verification readiness

`PhoneVerificationAuthority::ready()` is true only when the outbound SMS transport is configured and the shop has an inbound number; `EmailVerificationAuthority::ready()` only when a from address is set and managed mail can send. A product should offer only ready channels.

## Policy (Settings → Online Requests)

`shop_settings.online_booking` (normalized by `BookingSettings`) holds only what Core enforces:

- **Mode.** Requests (default): the customer picks a day and a time of day; nothing is reserved; staff pick the time. Confirmed: open start times inside scheduling hours, at least an hour ahead, within the concurrent limit (default one per schedulable bay) and the day's scheduling target; a booking that insists on approval still waits.
- **Several services in one visit.** Drop-off (longest item) or one after another (times add up).
- **Limits.** Most online bookings per day; whether days at the scheduling target are offered (confirmed bookings always respect capacity).
- **Service area.** ZIP codes for on-site work, and the message outside the area.

Request days, windows, horizon, notice and closed dates stay in Appointment Request Availability and its exceptions, so the Schedule's controls and this page are one rule.

## Constraints

`Contracts\BookingAvailabilityConstraint`, tagged `BookingConstraints::TAG`, is asked about every offered day and confirmed start, and again on submission, with the full context. A constraint is **required** unless it implements `AdvisoryBookingConstraint`. When a required one throws, no exact time is offered or confirmed, confirmed booking falls back to a request (keeping the asked-for time as "around 9:00 AM"), staff see "Not checked online: …", and the error is reported once per evaluation. A refusal from a check that did run still wins. `ServiceAreaConstraint` is Core's own.

## Staff

Pending requests appear under **Online requests waiting** on the Schedule, with every chosen item. **Pick a time** opens the normal appointment form with the preference, visit length, labor hours, notes and service address; saving it schedules the request and its lead. **Decline** closes the request and marks the lead lost. Rescheduling afterwards is the normal appointment reschedule. The service address is copied to the appointment (`service_location_type`, `service_address`).
