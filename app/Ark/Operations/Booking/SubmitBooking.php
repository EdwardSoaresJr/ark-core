<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentKind;
use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\RecordAppointmentFact;
use App\Ark\Operations\Appointments\ScheduleRequestWindows;
use App\Ark\Operations\Booking\Events\AppointmentRequestReceived;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadIngressContext;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\Leads\Public\ValidUsPhone;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves one online booking: the lead (Core's intake record), the appointment request, and in
 * instant mode the appointment itself.
 *
 * Every rule the form showed is checked again here, inside a lock on the shop's settings row, so
 * two customers cannot take the last opening at once and a resubmitted form saves nothing twice.
 */
final class SubmitBooking
{
    public function __construct(
        private readonly BookingAvailability $availability,
        private readonly BookingIdentity $identity,
        private readonly LeadRecorder $leads,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function execute(
        array $input,
        Session $identityStore,
        ?Customer $portalCustomer = null,
        ?LeadIngressContext $ingress = null,
        ?LeadState $forcedState = null,
        array $spamSignals = [],
    ): BookingOutcome {
        $settings = BookingSettings::forShop();

        if (! $settings['enabled']) {
            throw ValidationException::withMessages(['preferred_date' => 'Online booking is turned off. Call the shop and we will help you find a time.']);
        }

        [$data, $context, $service] = $this->validated($input, $settings);
        $mode = $context->mode($settings);
        $phone = PhoneNumber::normalize($data['contact_phone']) ?? $data['contact_phone'];

        if ($forcedState === LeadState::Spam) {
            $this->recordLead($data, $context, $mode, $phone, null, $ingress, $forcedState, $spamSignals);

            return BookingOutcome::quiet();
        }

        if (! $this->portalPhoneMatches($portalCustomer, $phone) && ! $this->identity->accept($identityStore, $phone, $data['contact_email'] ?? null)) {
            throw ValidationException::withMessages(['contact_phone' => 'Verify your phone number or email with the code before sending.']);
        }

        $vehicle = $this->portalVehicle($portalCustomer, (string) ($data['vehicle_selection'] ?? ''));

        $outcome = DB::transaction(function () use ($data, $context, $service, $mode, $phone, $portalCustomer, $vehicle, $ingress): BookingOutcome {
            ShopSettings::query()->lockForUpdate()->first();

            $key = (string) ($data['submission_key'] ?? '');
            if ($key !== '' && ($existing = AppointmentRequest::query()->where('submission_key', $key)->first())) {
                return BookingOutcome::for($existing);
            }

            $date = (string) $data['preferred_date'];
            $time = (string) ($data['preferred_time'] ?? '');
            $askedTime = null;

            if ($mode === BookingMode::Instant) {
                $refusal = $this->availability->refuseSlot($context, $date, $time);

                if ($this->availability->unchecked() !== []) {
                    $mode = BookingMode::Request;
                    $askedTime = $time !== '' ? $time : null;
                    $data['preferred_period'] = $this->fallbackPeriod($date, $time, (string) ($data['preferred_period'] ?? ''));
                    $refusal = $data['preferred_period'] === null
                        ? 'That day is no longer available. Pick another day.'
                        : $this->availability->refuseRequest($context, $date, $data['preferred_period']);
                } elseif ($time === '') {
                    $refusal = 'Pick a time.';
                }
            } else {
                $refusal = $this->availability->refuseRequest($context, $date, (string) $data['preferred_period']);
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
                throw ValidationException::withMessages([$mode === BookingMode::Instant ? 'preferred_time' : 'preferred_date' => $refusal]);
            }

            [$startsAt, $endsAt] = $mode === BookingMode::Instant
                ? $this->instantWindow($date, (string) $data['preferred_time'], $context)
                : [null, null];

            $lead = $this->recordLead($data, $context, $mode, $phone, $vehicle, $ingress, null, []);

            $request = AppointmentRequest::query()->create([
                'submission_key' => $key !== '' ? $key : null,
                'status' => AppointmentRequestStatus::Pending,
                'mode' => $mode,
                'lead_id' => $lead->id,
                'booking_service_id' => $service?->id,
                'service_name' => $service?->name,
                'service_option' => $data['service_option'] ?? null,
                'customer_id' => $vehicle['customer_id'] ?? ($this->portalPhoneMatches($portalCustomer, $phone) ? $portalCustomer?->id : null),
                'vehicle_id' => $vehicle['id'] ?? null,
                'contact_name' => $data['contact_name'],
                'contact_phone' => $phone,
                'contact_email' => $data['contact_email'] ?? null,
                'preferred_date' => $date,
                'preferred_period' => $mode === BookingMode::Request ? $data['preferred_period'] : null,
                'requested_starts_at' => $startsAt?->copy()->utc(),
                'requested_ends_at' => $endsAt?->copy()->utc(),
                'duration_minutes' => $context->durationMinutes(),
                'workload_hours' => $context->workloadHours(),
                'vehicle_count' => $context->vehicleCount,
                'vehicles' => $data['vehicles'],
                'concern' => $data['concern'],
                'answers' => $data['answers'],
                'location_mode' => $context->location,
                'service_address' => $context->location->isOnSite() ? $context->address : null,
                'context' => array_filter([
                    ...($data['context'] ?? []),
                    'asked_time' => $askedTime,
                    'unchecked' => $this->availability->unchecked() ?: null,
                ]) ?: null,
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

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: BookingContext, 2: BookingService|null}
     *
     * @throws ValidationException
     */
    public function validated(array $input, ?array $settings = null): array
    {
        $settings ??= BookingSettings::forShop();
        $fields = $settings['fields'];
        $services = BookingService::query()->offeredPublicly()->ordered()->get();

        $service = null;
        if ($services->isNotEmpty()) {
            $service = $services->firstWhere('id', (int) ($input['service_id'] ?? 0));
            if ($service === null) {
                throw ValidationException::withMessages(['service_id' => 'Choose a service.']);
            }
        }

        $option = $service?->option(is_string($input['service_option'] ?? null) ? $input['service_option'] : null);
        if ($service !== null && $service->optionList() !== [] && $option === null) {
            throw ValidationException::withMessages(['service_option' => 'Choose an option for '.$service->name.'.']);
        }

        $location = $this->locationFor($service, (string) ($input['location'] ?? ''));
        $mode = (new BookingContext($service))->mode($settings);
        $maxVehicles = $settings['max_vehicles'];
        $concernRequired = $fields['concern'] === BookingSettings::FIELD_REQUIRED || ($option['requires_details'] ?? false);

        $rules = [
            'concern_choice' => ['nullable', 'string', 'max:60'],
            'concern' => [$concernRequired ? 'required' : 'nullable', 'string', 'max:2000'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:32', new ValidUsPhone],
            'contact_email' => [$fields['email'] === BookingSettings::FIELD_REQUIRED ? 'required' : 'nullable', 'email', 'max:255'],
            'contact_preference' => ['nullable', Rule::enum(LeadContactPreference::class)],
            'vehicle_selection' => ['nullable', 'string', 'max:32'],
            'vehicles' => ['nullable', 'array', 'max:'.$maxVehicles],
            'vehicles.*.year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'vehicles.*.make' => ['nullable', 'string', 'max:80'],
            'vehicles.*.model' => ['nullable', 'string', 'max:80'],
            'vehicles.*.vin' => ['nullable', 'string', 'max:17'],
            'unsafe_to_drive' => ['nullable', 'boolean'],
            'needs_tow' => ['nullable', 'boolean'],
            'preferred_date' => ['required', 'date_format:Y-m-d'],
            'preferred_period' => $mode === BookingMode::Request
                ? ['required', 'string', Rule::in(AppointmentRequestAvailability::periodValues())]
                : ['nullable', 'string', Rule::in(AppointmentRequestAvailability::periodValues())],
            'preferred_time' => $mode === BookingMode::Instant ? ['nullable', 'date_format:H:i'] : ['nullable'],
            'submission_key' => ['nullable', 'string', 'max:64'],
        ];

        if ($location->isOnSite()) {
            $rules += [
                'address.line1' => ['required', 'string', 'max:160'],
                'address.line2' => ['nullable', 'string', 'max:160'],
                'address.city' => ['required', 'string', 'max:80'],
                'address.state' => ['nullable', 'string', 'max:40'],
                'address.postal_code' => ['required', 'string', 'max:12'],
                'address.notes' => ['nullable', 'string', 'max:500'],
            ];
        }

        $questions = BookingQuestion::query()->active()->get()->filter(fn (BookingQuestion $q): bool => $q->appliesTo($service?->id))->values();
        foreach ($questions as $question) {
            $rule = [$question->required ? 'required' : 'nullable'];
            $rule = [...$rule, ...match ($question->type) {
                BookingQuestionType::Choice => ['string', Rule::in($question->choiceList())],
                BookingQuestionType::YesNo => ['string', Rule::in(['yes', 'no'])],
                BookingQuestionType::Paragraph => ['string', 'max:1000'],
                default => ['string', 'max:200'],
            }];
            $rules['answers.'.$question->id] = $rule;
        }

        $attributes = ['address.line1' => 'street address', 'address.city' => 'city', 'address.postal_code' => 'ZIP code'];
        foreach ($questions as $question) {
            $attributes['answers.'.$question->id] = mb_strtolower($question->label);
        }

        $data = Validator::make($input, $rules, [
            'concern.required' => 'Tell us what the vehicle is doing.',
            'preferred_date.required' => 'Pick a day.',
            'preferred_period.required' => 'Pick a time of day.',
            'preferred_time.required' => 'Pick a time.',
        ], $attributes)->validate();

        $preference = isset($data['contact_preference']) ? LeadContactPreference::from($data['contact_preference']) : LeadContactPreference::Text;
        if ($preference === LeadContactPreference::Email && ! filled($data['contact_email'] ?? null)) {
            throw ValidationException::withMessages(['contact_email' => 'Enter an email address if you want email.']);
        }

        $vehicles = $this->vehicles($data['vehicles'] ?? [], $maxVehicles);
        if ($fields['vehicle'] === BookingSettings::FIELD_REQUIRED && $vehicles === [] && ! filled($data['vehicle_selection'] ?? null)) {
            throw ValidationException::withMessages(['vehicles.0.make' => 'Tell us the vehicle\'s year, make and model.']);
        }

        $address = [];
        if ($location->isOnSite()) {
            foreach (['line1', 'line2', 'city', 'state', 'postal_code', 'notes'] as $part) {
                $value = trim((string) ($data['address'][$part] ?? ''));
                if ($value !== '') {
                    $address[$part] = $value;
                }
            }
        }

        $context = new BookingContext(
            $service,
            $option['label'] ?? null,
            max(1, count($vehicles)),
            $location,
            $address,
        );

        $answers = [];
        foreach ($questions as $question) {
            $value = trim((string) ($data['answers'][$question->id] ?? ''));
            if ($value !== '') {
                $answers[] = ['question_id' => $question->id, 'label' => $question->label, 'answer' => $question->type === BookingQuestionType::YesNo ? ucfirst($value) : $value];
            }
        }

        $data['service_option'] = $option['label'] ?? null;
        $data['contact_preference'] = $preference;
        $data['vehicles'] = $vehicles;
        $data['answers'] = $answers !== [] ? $answers : null;
        $data['concern'] = $this->composeConcern($data, $service, $option['label'] ?? null, $settings);
        $data['context'] = array_filter([
            'page' => mb_substr((string) ($input['source_page'] ?? ''), 0, 60) ?: null,
            'host' => mb_substr((string) ($input['source_host'] ?? ''), 0, 255) ?: null,
            'embedded' => filter_var($input['embed'] ?? false, FILTER_VALIDATE_BOOL) ?: null,
            'unsafe_to_drive' => filter_var($data['unsafe_to_drive'] ?? false, FILTER_VALIDATE_BOOL) ?: null,
            'needs_tow' => filter_var($data['needs_tow'] ?? false, FILTER_VALIDATE_BOOL) ?: null,
        ]) ?: null;

        return [$data, $context, $service];
    }

    public function locationFor(?BookingService $service, string $chosen): ServiceLocationType
    {
        $mode = $service?->locationMode() ?? BookingLocationMode::Shop;

        return match ($mode) {
            BookingLocationMode::Shop => ServiceLocationType::Shop,
            BookingLocationMode::Mobile => $chosen === ServiceLocationType::FleetSite->value ? ServiceLocationType::FleetSite : ServiceLocationType::CustomerSite,
            BookingLocationMode::Either => ServiceLocationType::tryFrom($chosen) ?? ServiceLocationType::Shop,
        };
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
        $firstVehicle = $request->vehicle_id;

        $appointment = Appointment::query()->create([
            'customer_id' => $request->customer_id,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'vehicle_id' => $firstVehicle,
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordLead(
        array $data,
        BookingContext $context,
        BookingMode $mode,
        string $phone,
        ?array $vehicle,
        ?LeadIngressContext $ingress,
        ?LeadState $forcedState,
        array $spamSignals,
    ): Lead {
        $first = $data['vehicles'][0] ?? [];
        $date = (string) $data['preferred_date'];
        $period = $mode === BookingMode::Request ? (string) $data['preferred_period'] : AppointmentRequestAvailability::PERIOD_ANY;
        $dateLabel = Carbon::parse($date)->format('l, F j');
        $preferredLabel = $mode === BookingMode::Instant
            ? $dateLabel.' at '.Carbon::parse($date.' '.$data['preferred_time'])->format('g:i A').' (booked)'
            : PublicAppointmentRequest::preferredLabel($dateLabel, $period);

        return $this->leads->recordWebsiteSubmission([
            'source' => LeadSource::Website,
            'concern' => PublicAppointmentRequest::composeConcern($data['concern'], $preferredLabel),
            'contact_phone' => $phone,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_preference' => $data['contact_preference'],
            'vehicle_year' => $vehicle['year'] ?? ($first['year'] ?? null),
            'vehicle_make' => $vehicle['make'] ?? ($first['make'] ?? null),
            'vehicle_model' => $vehicle['model'] ?? ($first['model'] ?? null),
            'vehicle_vin' => $first['vin'] ?? null,
            'vehicle_id' => $vehicle['id'] ?? null,
            'customer_id' => $vehicle['customer_id'] ?? null,
            'preferred_period' => $period,
            'metadata' => PublicAppointmentRequest::metadataWithAvailability(array_filter([
                'public_page' => 'book',
                'public_host' => $data['context']['host'] ?? null,
                'booking_mode' => $mode->value,
                'location' => $context->location->value,
            ]), $date, $period, $preferredLabel),
        ], ingress: $ingress, forcedState: $forcedState, spamSignals: $spamSignals);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $settings
     */
    private function composeConcern(array $data, ?BookingService $service, ?string $option, array $settings): string
    {
        $parts = [];
        if ($service !== null) {
            $parts[] = $option !== null ? "{$service->name} ({$option})" : $service->name;
        }

        $choice = trim((string) ($data['concern_choice'] ?? ''));
        if ($choice !== '' && in_array($choice, $settings['concern_choices'], true)) {
            $parts[] = $choice;
        }

        $head = implode(' · ', $parts);
        $details = trim((string) ($data['concern'] ?? ''));

        $flags = array_filter([
            filter_var($data['unsafe_to_drive'] ?? false, FILTER_VALIDATE_BOOL) ? 'Unsafe to drive' : null,
            filter_var($data['needs_tow'] ?? false, FILTER_VALIDATE_BOOL) ? 'Needs a tow' : null,
        ]);

        $text = trim($head."\n\n".$details);
        if ($flags !== []) {
            $text .= "\n\n".implode(' · ', $flags);
        }

        return $text !== '' ? $text : 'Appointment request';
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{year: int|null, make: string|null, model: string|null, vin: string|null}>
     */
    private function vehicles(array $rows, int $max): array
    {
        $out = [];
        foreach (array_slice(array_values($rows), 0, $max) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $vehicle = [
                'year' => filled($row['year'] ?? null) ? (int) $row['year'] : null,
                'make' => filled($row['make'] ?? null) ? trim((string) $row['make']) : null,
                'model' => filled($row['model'] ?? null) ? trim((string) $row['model']) : null,
                'vin' => filled($row['vin'] ?? null) ? strtoupper(preg_replace('/\s+/', '', (string) $row['vin']) ?? '') : null,
            ];
            if (array_filter($vehicle) !== []) {
                $out[] = $vehicle;
            }
        }

        return $out;
    }

    private function portalPhoneMatches(?Customer $customer, string $phone): bool
    {
        if ($customer === null) {
            return false;
        }

        $customerPhone = PhoneNumber::normalize((string) ($customer->phone ?? ''));

        return $customerPhone !== null && $customerPhone === PhoneNumber::normalize($phone);
    }

    /**
     * @return array{id: int, customer_id: int, year: int|null, make: string, model: string}|null
     */
    private function portalVehicle(?Customer $customer, string $selection): ?array
    {
        if ($customer === null || ! ctype_digit($selection)) {
            return null;
        }

        $vehicle = $customer->vehicles()->whereKey((int) $selection)->first();

        return $vehicle === null ? null : [
            'id' => (int) $vehicle->id,
            'customer_id' => (int) $customer->id,
            'year' => $vehicle->year !== null ? (int) $vehicle->year : null,
            'make' => (string) $vehicle->make,
            'model' => (string) $vehicle->model,
        ];
    }
}
