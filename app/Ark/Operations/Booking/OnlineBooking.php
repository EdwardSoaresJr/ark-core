<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentKind;
use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\RecordAppointmentFact;
use App\Ark\Operations\Appointments\ScheduleRequestWindows;
use App\Ark\Operations\Booking\Events\AppointmentRequestReceived;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationLifecycle;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Core's contract for public booking products (ARK Shop's Book Appointment, or any other front end).
 *
 * A product asks what may be offered for what the customer chose so far, and submits the booking. Core
 * applies the scheduler's own rules (request days and windows, closed dates, the daily limit, capacity,
 * scheduling hours, concurrent appointments, the service area and tagged constraints), records the
 * lead and the appointment request, and books an appointment only when the shop takes confirmed
 * bookings, the booking does not insist on approval, the exact time is still open and every required
 * check ran. Everything is checked again inside a lock on the shop's settings row.
 *
 * Call it inside the shop (tenancy initialized). It sends nothing; AppointmentRequestReceived tells
 * the host a request arrived.
 */
final class OnlineBooking
{
    public function __construct(
        private readonly BookingAvailability $availability,
        private readonly LeadRecorder $leads,
    ) {}

    /**
     * Days, times of day or exact times, and the mode the customer will get.
     *
     * @return array<string, mixed> see BookingAvailability::forContext(), plus selected_date, periods and slots for $date
     */
    public function availability(BookingContext $context, ?string $date = null): array
    {
        $result = $this->availability->forContext($context);
        $open = array_column(array_filter($result['dates'], fn (array $d): bool => $d['available']), 'date');
        $date = in_array((string) $date, $open, true) ? (string) $date : ($open[0] ?? null);

        $result['selected_date'] = $date;
        $result['periods'] = $date !== null && $result['mode'] === BookingMode::Request->value ? $this->availability->periodsFor($date) : [];
        $result['slots'] = $date !== null && $result['mode'] === BookingMode::Instant->value ? $this->availability->slotsFor($date, $context) : [];

        return $result;
    }

    /**
     * @throws BookingRefused
     */
    public function submit(OnlineBookingRequest $input): BookingOutcome
    {
        $phone = PhoneNumber::normalize($input->contactPhone) ?? trim($input->contactPhone);
        if ($phone === '' || trim($input->contactName) === '') {
            throw new BookingRefused('contact_phone', 'Enter your name and a phone number we can reach.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input->preferredDate) !== 1) {
            throw new BookingRefused('preferred_date', 'Pick a day.');
        }

        $context = $input->booking;
        $mode = $context->mode(BookingSettings::forShop());
        $this->assertOwnership($input);

        if ($input->spam) {
            $this->recordLead($input, $phone, $mode, $input->preferredPeriod ?? AppointmentRequestAvailability::PERIOD_ANY, LeadState::Spam);

            return BookingOutcome::quiet();
        }

        $outcome = DB::transaction(function () use ($input, $context, $mode, $phone): BookingOutcome {
            ShopSettings::query()->lockForUpdate()->first();

            if ($input->submissionKey !== null && $input->submissionKey !== ''
                && ($existing = AppointmentRequest::query()->where('submission_key', $input->submissionKey)->first())) {
                return BookingOutcome::for($existing);
            }

            $date = $input->preferredDate;
            $time = (string) ($input->preferredTime ?? '');
            $period = (string) ($input->preferredPeriod ?? '');
            $askedTime = null;

            if ($mode === BookingMode::Instant) {
                $refusal = $this->availability->refuseSlot($context, $date, $time);

                if ($this->availability->unchecked() !== []) {
                    $mode = BookingMode::Request;
                    $askedTime = $time !== '' ? $time : null;
                    $period = (string) $this->fallbackPeriod($date, $time, $period);
                    $refusal = $period === ''
                        ? 'That day is no longer available. Pick another day.'
                        : $this->availability->refuseRequest($context, $date, $period);
                } elseif ($time === '') {
                    $refusal = 'Pick a time.';
                }
            } else {
                $refusal = $period === '' ? 'Pick a time of day.' : $this->availability->refuseRequest($context, $date, $period);
            }

            $duplicate = AppointmentRequest::query()
                ->where('contact_phone', $phone)
                ->whereDate('preferred_date', $date)
                ->where('status', AppointmentRequestStatus::Pending->value)
                ->first();
            if ($duplicate !== null && $mode === BookingMode::Request) {
                return BookingOutcome::for($duplicate);
            }

            if ($refusal !== null) {
                throw new BookingRefused($mode === BookingMode::Instant ? 'preferred_time' : 'preferred_date', $refusal);
            }

            [$startsAt, $endsAt] = $mode === BookingMode::Instant ? $this->instantWindow($date, $time, $context) : [null, null];

            $lead = $this->recordLead($input, $phone, $mode, $mode === BookingMode::Request ? $period : AppointmentRequestAvailability::PERIOD_ANY, null, $startsAt);

            $request = AppointmentRequest::query()->create([
                'submission_key' => $input->submissionKey ?: null,
                'status' => AppointmentRequestStatus::Pending,
                'mode' => $mode,
                'lead_id' => $lead->id,
                'service_reference' => $context->serviceReference,
                'service_name' => $context->serviceName,
                'service_option' => $context->option,
                'customer_id' => $input->customerId,
                'vehicle_id' => $input->vehicleId,
                'contact_name' => trim($input->contactName),
                'contact_phone' => $phone,
                'contact_email' => $input->contactEmail ?: null,
                'preferred_date' => $date,
                'preferred_period' => $mode === BookingMode::Request ? $period : null,
                'requested_starts_at' => $startsAt?->copy()->utc(),
                'requested_ends_at' => $endsAt?->copy()->utc(),
                'duration_minutes' => $context->durationMinutes(),
                'workload_hours' => $context->workloadHours(),
                'vehicle_count' => $context->vehicleCount,
                'vehicles' => $input->vehicles !== [] ? array_values($input->vehicles) : null,
                'concern' => $this->composeConcern($input),
                'answers' => $input->answers !== [] ? array_values($input->answers) : null,
                'selections' => $context->work !== [] ? array_map(fn (BookingWorkItem $item): array => $item->toArray(), $context->work) : null,
                'location_mode' => $context->location,
                'service_address' => $context->location->isOnSite() ? $context->address : null,
                'context' => array_filter([
                    ...$input->context,
                    'unsafe_to_drive' => $input->unsafeToDrive ?: null,
                    'needs_tow' => $input->needsTow ?: null,
                    'asked_time' => $askedTime,
                    'unchecked' => $this->availability->unchecked() ?: null,
                ], fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []) ?: null,
            ]);

            if ($mode === BookingMode::Instant) {
                $this->bookInstantly($request, $lead, $startsAt, $endsAt);
            }

            return BookingOutcome::for($request->refresh(), created: true);
        });

        if ($outcome->created && $outcome->request !== null) {
            AppointmentRequestReceived::dispatch($outcome->request->id);
        }

        return $outcome;
    }

    public function request(string $uuid): ?AppointmentRequest
    {
        return AppointmentRequest::query()->where('uuid', $uuid)->first();
    }

    /**
     * The time of day for an instant booking sent as a request: what the customer picked, else the
     * open window holding the time they asked for, else Flexible or the first open window.
     */
    private function fallbackPeriod(string $date, string $time, string $posted): ?string
    {
        $open = array_column($this->availability->periodsFor($date), 'value');

        if (in_array($posted, $open, true)) {
            return $posted;
        }

        foreach ($open as $period) {
            $window = ScheduleRequestWindows::windowForPeriod($period);
            if ($time !== '' && $window !== null && $time >= $window['open'] && $time < $window['close']) {
                return $period;
            }
        }

        return in_array(AppointmentRequestAvailability::PERIOD_ANY, $open, true)
            ? AppointmentRequestAvailability::PERIOD_ANY
            : ($open[0] ?? null);
    }

    private function bookInstantly(AppointmentRequest $request, Lead $lead, Carbon $startsAt, Carbon $endsAt): void
    {
        $appointment = Appointment::query()->create([
            'customer_id' => $request->customer_id,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'vehicle_id' => $request->vehicle_id,
            'lead_id' => $lead->id,
            'starts_at' => $startsAt->copy()->utc(),
            'ends_at' => $endsAt->copy()->utc(),
            'estimated_labor_hours' => $request->workload_hours,
            'concern' => mb_substr($request->concern, 0, 1000),
            'notes' => $request->staffNotes(),
            'status' => AppointmentStatus::Scheduled,
            'kind' => AppointmentKind::Intake,
            'service_location_type' => $request->locationType()->value,
            'service_address' => $request->service_address,
        ]);

        $request->forceFill([
            'status' => AppointmentRequestStatus::Scheduled,
            'appointment_id' => $appointment->id,
            'decided_at' => now(),
        ])->save();

        $lead->forceFill(['state' => LeadState::Scheduled, 'scheduled_at' => now()])->save();

        app(RecordAppointmentFact::class)->execute($appointment, OperationalEventName::AppointmentScheduled, null, ['source' => 'online_booking']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function instantWindow(string $date, string $time, BookingContext $context): array
    {
        $startsAt = Carbon::parse($date.' '.$time, ShopDisplayTimezone::resolve());

        return [$startsAt, $startsAt->copy()->addMinutes($context->durationMinutes())];
    }

    private function recordLead(OnlineBookingRequest $input, string $phone, BookingMode $mode, string $period, ?LeadState $forcedState, ?Carbon $startsAt = null): Lead
    {
        $first = $input->vehicles[0] ?? [];
        $date = $input->preferredDate;
        $dateLabel = Carbon::parse($date)->format('l, F j');
        $preferredLabel = $mode === BookingMode::Instant && $startsAt !== null
            ? $dateLabel.' at '.$startsAt->format('g:i A').' (booked)'
            : PublicAppointmentRequest::preferredLabel($dateLabel, $period);

        return $this->leads->recordWebsiteSubmission([
            'source' => LeadSource::Website,
            'concern' => PublicAppointmentRequest::composeConcern($this->composeConcern($input), $preferredLabel),
            'contact_phone' => $phone,
            'contact_name' => trim($input->contactName),
            'contact_email' => $input->contactEmail,
            'contact_preference' => $input->contactPreference,
            'vehicle_year' => $first['year'] ?? null,
            'vehicle_make' => $first['make'] ?? null,
            'vehicle_model' => $first['model'] ?? null,
            'vehicle_vin' => $first['vin'] ?? null,
            'vehicle_id' => $input->vehicleId,
            'customer_id' => $input->customerId,
            'preferred_period' => $period,
            'metadata' => PublicAppointmentRequest::metadataWithAvailability(array_filter([
                'public_page' => 'book',
                'public_host' => $input->context['host'] ?? null,
                'booking_mode' => $mode->value,
                'location' => $input->booking->location->value,
            ]), $date, $period, $preferredLabel),
        ], ingress: $input->ingress, forcedState: $forcedState, spamSignals: $input->spamSignals);
    }

    /**
     * Saved vehicles and recommended work must belong to the customer the product recognized.
     *
     * @throws BookingRefused
     */
    private function assertOwnership(OnlineBookingRequest $input): void
    {
        $vehicleIds = array_values(array_unique(array_filter([
            $input->vehicleId,
            ...array_map(fn (array $vehicle): mixed => $vehicle['vehicle_id'] ?? null, $input->vehicles),
            ...array_map(fn (BookingWorkItem $item): ?int => $item->vehicleId, $input->booking->work),
        ], fn (mixed $id): bool => $id !== null)));
        $recommendationIds = array_values(array_filter(array_map(fn (BookingWorkItem $item): ?int => $item->recommendationId, $input->booking->work)));

        if ($vehicleIds === [] && $recommendationIds === []) {
            return;
        }

        if ($input->customerId === null
            || Vehicle::query()->where('customer_id', $input->customerId)->whereIn('id', $vehicleIds)->count() !== count($vehicleIds)) {
            throw new BookingRefused('vehicle', 'Choose one of your vehicles or add a new one.');
        }

        $open = Recommendation::query()
            ->where('customer_id', $input->customerId)
            ->where('lifecycle', RecommendationLifecycle::Open->value)
            ->whereIn('id', $recommendationIds)
            ->pluck('vehicle_id', 'id');

        foreach ($input->booking->work as $item) {
            if ($item->recommendationId !== null
                && (! $open->has($item->recommendationId) || ($item->vehicleId !== null && (int) $open[$item->recommendationId] !== $item->vehicleId))) {
                throw new BookingRefused('recommendations', 'Some recommended work is no longer available. Review your selections.');
            }
        }
    }

    private function composeConcern(OnlineBookingRequest $input): string
    {
        $work = $input->booking->work;
        $head = count($work) > 1
            ? implode("\n", array_map(fn (BookingWorkItem $item): string => '• '.($item->kind === BookingWorkItem::RECOMMENDATION ? 'Recommended: ' : '').$item->label()
                .(filled($item->details) ? ' — '.trim((string) $item->details) : ''), $work))
            : (string) $input->booking->serviceLabel();
        $details = trim($input->concern);
        $flags = array_filter([
            $input->unsafeToDrive ? 'Unsafe to drive' : null,
            $input->needsTow ? 'Needs a tow' : null,
        ]);

        $text = trim($head."\n\n".$details);
        if ($flags !== []) {
            $text .= "\n\n".implode(' · ', $flags);
        }

        return $text !== '' ? mb_substr($text, 0, 4000) : 'Appointment request';
    }
}
