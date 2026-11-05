# Online booking

Core owns booking: the shop's settings, the customer's form and popup, the request record, and how it reaches the Schedule. ARK Shop and ARK Sites present it (theme, website buttons) and may notify people about it. They never keep a second calendar.

## What customers get

- `/book` on the shop's customer host, with or without a published website. Full page for shared links, texts, and social posts.
- `/book?embed=1`, the same form without the site header, for the popup.
- `/book/popup.js` turns every link to `/book` on that host, and every element with `data-ark-book`, into a dialog that loads the embedded form.
  - Core's website pages and ARK Sites templates load it automatically, through the customer shell.
  - Another site (WordPress, an imported site) pastes the snippet from Settings → Online booking → Website popup.
  - `?concern=`, `?service=`, `data-concern`, and `data-service` prefill the form.
  - Modified clicks, browsers without `<dialog>`, and sites the shop has not listed open the full page instead.
- The form works without JavaScript. With it, the form shows one step at a time, and the day and time choices follow the chosen service, vehicle count, and address.
- The receipt says plainly whether the customer has a request (the shop confirms the time) or a confirmed appointment.
- Contact Us is unchanged. It stays a general question to the shop.

## Settings → Online booking

| Tab | Controls |
| --- | --- |
| Booking form | On or off. Requests or confirmed bookings. Title, introduction, instructions, the two confirmation messages. Email, vehicle, and description required, optional, or not asked. VIN, contact preference, and safe-to-drive questions. Vehicles per booking. The concern choices customers tap. |
| Services | Name, description, visit length, labor hours, the hint in the description box, where the work happens (shop, customer's location, either), offered online, always needs approval, and options (Front / Rear / Both) with their own visit length and "details required". Order. Starter services. |
| Questions | Short answer, paragraph, pick one, yes or no. Required or not, for every service or only some. |
| Availability | Summary of the request days and windows (edited under Appointments, unchanged). Holidays and closed dates. Most bookings per day. Concurrent appointments for confirmed bookings. Whether full days are offered. Service area by ZIP code. |
| Website popup | Booking link, embed snippet, button label, floating button, the other websites allowed to show the popup. |

Storage: `shop_settings.online_booking` (normalized by `BookingSettings`), `booking_services`, `booking_questions`. Request days, windows, horizon, and notice stay in `appointment_request_availability`, and dates in `appointment_request_exceptions`, so the Schedule's "accept requests this day" control and Settings stay one rule.

## Requests and confirmed bookings

Each submission records a lead (the intake record Core already keeps, `LeadSource::Website`) and an `appointment_requests` row.

**Request mode (the default).**

- The customer picks a day and a time of day.
- The request reserves no capacity and books nothing.
- It waits in **Online requests waiting** on the Schedule.
- **Pick a time** opens the normal appointment form with the request's preference, visit length, labor hours, notes, and service address.
- Saving the appointment marks the request scheduled and its lead scheduled.
- **Decline** closes the request and marks its lead lost.
- Rescheduling afterwards is the normal appointment reschedule.

**Confirmed bookings.** Only when the shop chooses it, and never for a service marked "always needs approval".

- The customer sees open start times only:
  - inside scheduling hours, with the slot step;
  - not past the latest arrival;
  - at least an hour from now;
  - at most the concurrent-appointments limit (default one per schedulable bay);
  - never past the day's scheduling target.
- The appointment is created with no staff creator.

**Both modes.** The form shows a day only when:

- request availability allows it (weekly days, closed dates, horizon, notice);
- the daily booking limit is not reached;
- unless the shop turned it off, the day's soft capacity (`SchedulingCapacityCalculator`) has room for the service.

Submission repeats every check inside a lock on the shop's settings row. A resubmitted form, or a second pending request for the same phone and day, returns the first request. Two customers cannot take the last opening.

## Abuse and privacy

- The form keeps no session. Inside another site's iframe the browser may send no cookies, so:
  - it carries an encrypted render stamp (spam when missing, too fast under 3 seconds, stale after a day);
  - it has a honeypot field;
  - it posts to `/book` without CSRF, at most 10 posts a minute per address (the `online-booking` limiter). Code requests and checks count. Past the limit, the customer gets a "Too many tries" page that the popup can show;
  - the render stamp keeps the form's first render time when the form comes back after a code request, a code check or an error, so a customer who verifies and sends at once is not told to wait;
  - a signed-in portal customer is trusted only when the post also carries their session's CSRF token.
- The receipt is a signed link valid for two hours, bound to the shop's host.
- Embedded pages send `frame-ancestors` for the shop's own host and its listed domains only, and no `X-Frame-Options`. A web server in front of Core that adds `X-Frame-Options` to every response must leave it off responses that already send `frame-ancestors`. Current Chromium and WebKit (Safari) browsers follow `frame-ancestors` either way (tested), but a browser that only understands `X-Frame-Options` would show a blank popup.
- Identity verification is unchanged: an ARK Shop code by text or email when the shop can send one. Because cookies may be missing, the proof travels in an encrypted form field and is consumed once. A proof whose verification was already used is refused.
- Core sends no confirmation or notification. It dispatches `AppointmentRequestReceived` and `AppointmentRequestDecided`; ARK Shop may tell the shop and the customer.

## Mobile and fleet groundwork (ARK Shop #95, #96)

- **Where the work happens.**
  - Each service says shop, customer's location, or either.
  - On-site requests require a service address with access notes.
  - The address is stored on the request (`service_address`, `location_mode`: `shop`, `customer_site`, `fleet_site`) and copied to the appointment it becomes (`appointments.service_location_type`, `appointments.service_address`).
  - The appointment page shows it.
- **Service area.** ZIP codes in Core (`ServiceAreaConstraint`). An address outside the area is told so, and nothing is offered.
- **Several vehicles.** Up to the shop's limit, stored per vehicle on the request. Visit length and workload scale with the count, so capacity sees a fleet visit as the work it is.
- **The extension point.** `App\Ark\Operations\Booking\Contracts\BookingAvailabilityConstraint`, tagged `BookingConstraints::TAG`, is asked about every offered day and every instant start. Submission asks it again. It receives the context: service, option, vehicle count, location, and address. That is where ARK Shop adds:
  - drive time between stops;
  - radius or polygon service areas;
  - a technician's or van's own calendar;
  - route-aware windows.

  A constraint is **required** unless it implements `AdvisoryBookingConstraint`. When a required constraint throws (a maps provider is down, a technician calendar times out):

  - no exact time is offered or confirmed, in the form or on submission;
  - a shop that takes confirmed bookings shows the request form instead, with the notice "We can't confirm times online right now, so this will be a request…";
  - a customer who already picked a time is sent as a request with everything they entered, the time of day holding that time, and "around 9:00 AM" on the request;
  - the receipt says "Not confirmed yet" and why;
  - staff see "Not checked online: Travel time" on the waiting request and in the appointment notes;
  - the error is reported, and logged with the constraint, date, service and location. It is asked once per evaluation, not once per day and start time.

  A refusal from a check that did run still wins: an address outside the ZIP list is refused even while travel time is down. An advisory constraint (one that only prefers some days, never decides whether the work can be done) that throws is reported and treated as allowing the candidate.
- **Not built here:** routing, dispatch, stop order, per-vehicle repair orders for one visit, and Google Calendar sync. A future visit/stop model can group requests by `service_address` and `vehicles` without changing how customers book.

## Recovered from the original Book Appointment (V1, removed in ark-stancl `a249ec5`)

| V1 Book Service Builder | Now |
| --- | --- |
| Services with duration, branch options (two levels) with their own duration and "details required" | Services with duration, labor hours, one level of options with duration and "details required". A second level can be a "pick one" question limited to that service. |
| Intake-first or service-first flow | No services: concern first. With services: service, then concern. |
| Collect service address (mobile) | Per service: shop, customer's location, or either. Service area by ZIP. |
| Customer booking messages per step, popup title, subtitle, header color | Title, introduction, instructions, request and confirmed messages. Colors come from the shop's published theme (`CustomerThemeSource`). |
| Allowed embed domains, 403 for other sites | Website popup allowed domains, `frame-ancestors`. Other sites get the full page. |
| `book_script.js` popup, floating button, button text, WordPress plugin shortcode | `/book/popup.js`, floating button, button label, any link or `data-ark-book` element. One snippet replaces the plugin. |
| Booking link and embed snippet | Website popup tab. |
| Phone verification by SMS code | Unchanged ARK Shop identity check (text or email), carried in the form so it works in iframes. |
| Unsafe to drive, needs tow, VIN/plate | Safe-to-drive questions, VIN, existing vehicles for signed-in customers. |
| Preferred date and morning/midday/afternoon, or exact slots | Request windows from request availability, or exact open times (instant). |
| Slots from shop hours and holidays; overlap with any appointment refused | Scheduling hours, closed dates, concurrency limit, soft capacity, and the constraint extension. |
| Status `requested` appointment, staff confirm in Scheduler | `appointment_requests` waiting on the Schedule; staff schedule it with the normal form. |
| Emails and texts to the shop and the customer | ARK Shop, from the two events. Core sends nothing. |
| Facebook post text, short links, UTM | Not restored. The booking link works in any post. |
| Unused per-service price, cancel-before, auto-approve | Not restored. Auto-approve became the shop's confirmed-booking mode plus "always needs approval". |
| A line of copy for each of the 7 V1 steps | Title, introduction, instructions, the two confirmation messages, each service's description hint, and each question's help text. The form has fewer steps, and the steps it has are labeled by what they ask. |

### Why one level of options is enough

In V1 the second level was not nested under the first. It was a second list on the same service. V1's seeder created first-level options only, so shops began with one level. When a shop did use both levels, the customer picked one option from each list, and the visit length came from the second pick, else the first, else the service. For example, a shop could set up Brakes with Front, Rear or Full, then Pads or Rotor resurface.

Both uses of that second list are covered without bringing back a second option list:

- **When the second pick changes how long the visit takes**, list the combinations as options: "Front pads 60 min", "Front pads and rotors 90 min", "Rear pads 60 min". Each combination gets its own visit length and labor hours. V1 could not do that, because there only one level's time counted.
- **When the second pick only tells the shop more**, use a "pick one" question limited to that service, for example "What do you need? Pads / Rotors / Not sure". It can be required, its answer is on the request and in the appointment notes, and staff set the time.

With only a handful of options per service, the combined list stays short. A second option list that changes visit length becomes worth adding only when a shop needs many combinations of two lists, each with a different visit length. That would be a new feature, not a V1 behavior to restore.
