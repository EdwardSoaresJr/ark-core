<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentRequestAvailabilityProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\LeadContactNameParser;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Leads\LeadIngressContext;
use App\Ark\Operations\Leads\LeadIngressHygiene;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\WebsiteLeadInterruptBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicLeadStoreController
{
    public function __construct(
        private readonly LeadRecorder $leads,
        private readonly LeadIngressHygiene $ingressHygiene,
        private readonly LeadPhoneVerification $phoneVerification,
        private readonly LeadEmailVerification $emailVerification,
        private readonly PublicLeadFormContactPrefill $contactPrefill,
        private readonly PublicSurfaceEventRecorder $surfaceEvents,
        private readonly WebsiteLeadInterruptBroadcaster $websiteLeadInterrupts,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        if (filled($request->input('company_website'))) {
            return redirect()->route('public.leads.thanks');
        }

        if (
            $request->boolean('contact_inquiry')
            || $request->input('public_surface_variant') === 'contact_inquiry'
        ) {
            return $this->storeContactInquiry($request);
        }

        // /book and other surfaces that collect preferred_date/period (e.g. Common Problems Book form).
        $isAppointmentRequest = ($request->input('public_surface_page') === PublicAppointmentRequest::SURFACE_PAGE)
            || $request->filled('preferred_date');
        $availabilityProjection = $isAppointmentRequest
            ? app(AppointmentRequestAvailabilityProjection::class)->forBook()
            : null;
        $allowedDates = $availabilityProjection !== null
            ? array_column($availabilityProjection['dates'], 'date')
            : [];

        $validated = $request->validate([
            'concern' => ['required', 'string', 'max:5000'],
            'preferred_date' => [
                Rule::requiredIf($isAppointmentRequest),
                'nullable',
                'date_format:Y-m-d',
                Rule::in($allowedDates !== [] ? $allowedDates : ['__none__']),
            ],
            'preferred_period' => [
                Rule::requiredIf($isAppointmentRequest),
                'nullable',
                'string',
                Rule::in(AppointmentRequestAvailability::periodValues()),
            ],
            'phone' => ['required', 'string', 'max:32', new ValidUsPhone],
            'first_name' => ['required', 'string', 'max:128'],
            // Optional: book wizard may collect a single "Name". contactNameFromParts
            // omits placeholder/empty surnames so "—" never becomes the public contact_name.
            'last_name' => ['nullable', 'string', 'max:128'],
            'email' => [
                Rule::requiredIf(fn (): bool => $request->input('contact_preference', LeadContactPreference::Text->value) === LeadContactPreference::Email->value),
                'nullable',
                'email',
                'max:255',
            ],
            'contact_preference' => ['nullable', Rule::enum(LeadContactPreference::class)],
            'vehicle_selection' => ['nullable', 'string', 'max:32'],
            'vehicle_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'vehicle_make' => ['nullable', 'string', 'max:64'],
            'vehicle_model' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'form_rendered_at' => ['nullable', 'integer', 'min:1'],
            'public_surface_page' => ['nullable', 'string', 'max:128'],
            'public_surface_variant' => ['nullable', 'string', 'max:32'],
            'public_surface_placement' => ['nullable', 'string', 'max:32'],
            'still_on_radar' => ['nullable', 'array', 'max:8'],
            'still_on_radar.*' => ['integer', 'min:1'],
        ]);

        if ($isAppointmentRequest && $allowedDates === []) {
            throw ValidationException::withMessages([
                'preferred_date' => 'We’re not accepting online appointment requests right now. Call or text us instead.',
            ]);
        }

        $contactPreference = isset($validated['contact_preference'])
            ? LeadContactPreference::from($validated['contact_preference'])
            : LeadContactPreference::Text;

        $signedInPhoneTrusted = $this->contactPrefill->phoneMatchesSignedInCustomer($validated['phone']);
        $selectedVehicle = null;
        $vehicleSelection = (string) ($validated['vehicle_selection'] ?? '');

        if ($vehicleSelection !== '' && $vehicleSelection !== 'other') {
            $selectedVehicle = $this->contactPrefill->resolveOwnedVehicle($vehicleSelection);

            if ($selectedVehicle === null) {
                return back()
                    ->withInput()
                    ->withErrors(['vehicle_selection' => 'Choose one of your vehicles, or enter a different one.']);
            }
        }

        $bookIdentityRequired = $isAppointmentRequest && (
            $this->phoneVerification->bookIdentityGateReady() || $this->emailVerification->ready()
        );

        if (! $signedInPhoneTrusted) {
            if ($bookIdentityRequired) {
                $emailProof = filled($validated['email'] ?? null)
                    ? $this->emailVerification->consumeBookProof($request->session(), (string) $validated['email'])
                    : $this->emailVerification->consumeBookProof($request->session());
                $phoneProof = $this->phoneVerification->consumeBookProof($request->session(), $validated['phone']);

                if (! $phoneProof && ! $emailProof) {
                    return back()
                        ->withInput()
                        ->withErrors(['phone' => 'Verify your phone number or email with the code before sending.']);
                }
            } elseif (
                $this->phoneVerification->required()
                && ! $this->phoneVerification->consume($request->session(), $validated['phone'])
            ) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => 'Verify your phone number with the text code before sending.']);
            }
        }

        $preferredDate = trim((string) ($validated['preferred_date'] ?? ''));
        $preferredPeriod = trim((string) ($validated['preferred_period'] ?? AppointmentRequestAvailability::PERIOD_ANY));
        $preferredLabel = '';

        if ($isAppointmentRequest && $preferredDate !== '') {
            $dateLabel = collect($availabilityProjection['dates'] ?? [])
                ->firstWhere('date', $preferredDate)['label'] ?? $preferredDate;
            $preferredLabel = PublicAppointmentRequest::preferredLabel($dateLabel, $preferredPeriod);
        }

        $concern = $isAppointmentRequest && $preferredLabel !== ''
            ? PublicAppointmentRequest::composeConcern($validated['concern'], $preferredLabel)
            : $validated['concern'];

        $surfaceMetadata = PublicSurfaceContext::fromInput([
            'page' => $validated['public_surface_page'] ?? null,
            'variant' => $validated['public_surface_variant'] ?? null,
            'placement' => $validated['public_surface_placement'] ?? null,
        ]);

        $metadata = $surfaceMetadata !== null
            ? ['public_surface' => $surfaceMetadata->toArray()]
            : [];

        if ($isAppointmentRequest && $preferredDate !== '') {
            $metadata = PublicAppointmentRequest::metadataWithAvailability(
                $metadata,
                $preferredDate,
                $preferredPeriod,
                $preferredLabel,
            );
        }

        $portalCustomer = Auth::guard('portal')->user();
        $linkCustomer = $portalCustomer instanceof Customer && $signedInPhoneTrusted;

        // Presentation-only carry of deferred items the customer opted to mention.
        // Does not mutate RO / concern disposition authority.
        if ($selectedVehicle !== null && $portalCustomer instanceof Customer) {
            $radarIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                $validated['still_on_radar'] ?? [],
            )));
            $radarMeta = $this->stillOnRadarMetadata($selectedVehicle->id, $radarIds);

            if ($radarMeta !== []) {
                $metadata['still_on_radar'] = $radarMeta;
            }
        }

        return $this->finishLead(
            $request,
            [
                'concern' => $concern,
                'contact_phone' => $validated['phone'],
                'contact_name' => LeadContactNameParser::contactNameFromParts(
                    $validated['first_name'],
                    $validated['last_name'] ?? '',
                ),
                'contact_email' => $validated['email'] ?? null,
                'contact_preference' => $contactPreference,
                'customer_id' => $linkCustomer ? $portalCustomer->id : null,
                'vehicle_id' => $selectedVehicle?->id,
                'vehicle_year' => $selectedVehicle?->year ?? ($validated['vehicle_year'] ?? null),
                'vehicle_make' => $selectedVehicle?->make ?? ($validated['vehicle_make'] ?? null),
                'vehicle_model' => $selectedVehicle?->model ?? ($validated['vehicle_model'] ?? null),
                'vehicle_vin' => $selectedVehicle?->authoritativeVin(),
                'source' => isset($validated['source'])
                    ? LeadSource::from($validated['source'])
                    : LeadSource::Website,
                'metadata' => $metadata !== [] ? $metadata : null,
            ],
            $surfaceMetadata,
        );
    }

    private function storeContactInquiry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', new ValidUsPhone],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'form_rendered_at' => ['nullable', 'integer', 'min:1'],
            'public_surface_page' => ['nullable', 'string', 'max:128'],
            'public_surface_variant' => ['nullable', 'string', 'max:32'],
            'public_surface_placement' => ['nullable', 'string', 'max:32'],
        ]);

        $nameParts = LeadContactNameParser::split($validated['name']);
        $firstName = $nameParts['first_name'] !== '' ? $nameParts['first_name'] : trim($validated['name']);
        $lastName = LeadContactNameParser::normalizeLastName($nameParts['last_name']);
        $phone = trim((string) ($validated['phone'] ?? ''));
        $subject = trim($validated['subject']);
        $message = trim($validated['message']);

        $surfaceMetadata = PublicSurfaceContext::fromInput([
            'page' => $validated['public_surface_page'] ?? 'contact',
            'variant' => $validated['public_surface_variant'] ?? 'contact_inquiry',
            'placement' => $validated['public_surface_placement'] ?? 'embedded_form',
        ]);

        $metadata = $surfaceMetadata !== null
            ? ['public_surface' => $surfaceMetadata->toArray()]
            : [];
        $metadata['contact_inquiry'] = [
            'subject' => $subject,
        ];

        $concern = "Subject: {$subject}\n\n{$message}";

        return $this->finishLead(
            $request,
            [
                'concern' => $concern,
                'contact_phone' => $phone !== '' ? $phone : null,
                'contact_name' => LeadContactNameParser::contactNameFromParts($firstName, $lastName),
                'contact_email' => $validated['email'],
                'contact_preference' => LeadContactPreference::Email,
                'customer_id' => null,
                'vehicle_id' => null,
                'vehicle_year' => null,
                'vehicle_make' => null,
                'vehicle_model' => null,
                'vehicle_vin' => null,
                'source' => isset($validated['source'])
                    ? LeadSource::from($validated['source'])
                    : LeadSource::Website,
                'metadata' => $metadata,
            ],
            $surfaceMetadata,
        );
    }

    /**
     * @param  list<int>  $concernIds
     * @return list<array{concern_id: int, repair_order_id: int, summary: string}>
     */
    private function stillOnRadarMetadata(int $vehicleId, array $concernIds): array
    {
        if ($concernIds === []) {
            return [];
        }

        $orders = RepairOrder::query()
            ->where('vehicle_id', $vehicleId)
            ->with('concerns')
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($concernIds as $concernId) {
            foreach ($orders as $order) {
                $concern = $order->concerns->firstWhere('id', $concernId);

                if ($concern === null) {
                    continue;
                }

                if ($concern->disposition !== RepairOrderConcernDisposition::Deferred) {
                    continue;
                }

                $summary = trim((string) $concern->summary);

                if ($summary === '') {
                    continue;
                }

                $items[] = [
                    'concern_id' => (int) $concern->id,
                    'repair_order_id' => (int) $order->id,
                    'summary' => $summary,
                ];
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function finishLead(
        Request $request,
        array $data,
        ?PublicSurfaceContext $surfaceMetadata,
    ): RedirectResponse {
        $ingress = LeadIngressContext::fromRequest($request);
        $spamSignals = $this->ingressHygiene->signals($ingress);
        $state = $this->ingressHygiene->autoSpamState($spamSignals) ?? LeadState::Received;

        if ($request->hasSession()) {
            $this->surfaceEvents->record(
                (string) $request->session()->getId(),
                PublicSurfaceEventType::LeadSubmitted,
                context: $surfaceMetadata?->toArray(),
                request: $request,
            );
        }

        $lead = $this->leads->recordWebsiteSubmission(
            data: $data,
            ingress: $ingress,
            forcedState: $state,
            spamSignals: $spamSignals,
        );

        if ($state !== LeadState::Spam && $request->hasSession()) {
            $this->surfaceEvents->record(
                (string) $request->session()->getId(),
                PublicSurfaceEventType::LeadCreated,
                $lead->id,
                context: $surfaceMetadata?->toArray(),
                request: $request,
            );
        }

        $redirect = redirect()->route('public.leads.thanks');

        if ($state !== LeadState::Spam) {
            $this->websiteLeadInterrupts->broadcast($lead);
            $redirect->with('website_lead_thanks_uuid', $lead->uuid);
        }

        return $redirect;
    }
}
