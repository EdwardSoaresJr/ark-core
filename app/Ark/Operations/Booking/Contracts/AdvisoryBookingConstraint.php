<?php

namespace App\Ark\Operations\Booking\Contracts;

/**
 * A constraint whose failure must not stop booking, because it never decides whether the work can be
 * done (for example, preferring days a technician is already nearby). Its refusals still apply; when it
 * throws, it is reported and the candidate is treated as allowed.
 */
interface AdvisoryBookingConstraint extends BookingAvailabilityConstraint {}
