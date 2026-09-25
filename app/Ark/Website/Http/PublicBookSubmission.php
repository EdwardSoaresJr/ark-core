<?php

namespace App\Ark\Website\Http;

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use App\Ark\Operations\EmailVerification\EmailVerificationException;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\Public\LeadEmailVerification;
use App\Ark\Operations\Leads\Public\LeadPhoneVerification;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\Leads\Public\PublicBookWizardConcerns;
use App\Ark\Operations\Leads\Public\ValidUsPhone;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\PhoneVerification\PhoneVerificationException;
use App\Ark\Operations\Portal\PortalObservationSession;
use App\Ark\Operations\Portal\ResolveCustomerByContact;
use App\Ark\Website\PublishedWebsite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class PublicBookSubmission
{
    public function __construct(
        private readonly LeadRecorder $leads,
        private readonly LeadPhoneVerification $phones,
        private readonly LeadEmailVerification $emails,
        private readonly PhoneVerificationAuthority $phoneAuthority,
        private readonly EmailVerificationAuthority $emailAuthority,
        private readonly CustomerRecognitionProjection $recognition,
    ) {}

    public function store(Request $request, PublishedWebsite $website): RedirectResponse
    {
        $availability = PublicAppointmentRequest::availabilityProjection();
        $accepting = (bool) ($availability['accepting_requests'] ?? false);
        $allowedDates = array_column($availability['dates'] ?? [], 'date');
        $periods = array_column($availability['periods'] ?? [], 'value');

        if ($request->input('book_intent') === 'send_phone_code') {
            return $this->sendPhoneCode($request);
        }

        if ($request->input('book_intent') === 'check_phone_code') {
            return $this->checkPhoneCode($request);
        }

        if ($request->input('book_intent') === 'send_email_code') {
            return $this->sendEmailCode($request);
        }

        if ($request->input('book_intent') === 'check_email_code') {
            return $this->checkEmailCode($request);
        }

        if (! $accepting || $allowedDates === []) {
            return back()->withInput()->withErrors([
                'preferred_date' => 'Online appointment requests are paused. Call or text the shop and we will help you find a time.',
            ]);
        }

        $data = $request->validate([
            'concern_category' => ['required', 'string', Rule::in(PublicBookWizardConcerns::options())],
            'concern_details' => ['nullable', 'string', 'max:2000'],
            'preferred_date' => ['required', 'date_format:Y-m-d', Rule::in($allowedDates)],
            'preferred_period' => ['required', 'string', Rule::in($periods !== [] ? $periods : AppointmentRequestAvailability::periodValues())],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:32', new ValidUsPhone],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_preference' => ['nullable', Rule::enum(LeadContactPreference::class)],
            'vehicle_selection' => ['nullable', 'string', 'max:32'],
            'vehicle_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'vehicle_make' => ['nullable', 'string', 'max:80'],
            'vehicle_model' => ['nullable', 'string', 'max:80'],
        ]);

        $preference = isset($data['contact_preference'])
            ? LeadContactPreference::from($data['contact_preference'])
            : LeadContactPreference::Text;

        if ($preference === LeadContactPreference::Email && ! filled($data['contact_email'] ?? null)) {
            return back()->withInput()->withErrors([
                'contact_email' => 'Enter an email address if you want email.',
            ]);
        }

        $concern = PublicBookWizardConcerns::composeConcern(
            $data['concern_category'],
            $data['concern_details'] ?? '',
        );

        if ($concern === '') {
            return back()->withInput()->withErrors([
                'concern_details' => 'Tell us what the car is doing.',
            ]);
        }

        $vehicle = $this->selectedVehicle($data['vehicle_selection'] ?? '');
        if (($data['vehicle_selection'] ?? '') !== '' && ($data['vehicle_selection'] ?? '') !== 'other' && $vehicle === null) {
            return back()->withInput()->withErrors([
                'vehicle_selection' => 'Choose one of your vehicles, or enter a different one.',
            ]);
        }

        if (! $this->identityAccepted($request, $data['contact_phone'], $data['contact_email'] ?? null)) {
            return back()->withInput()->withErrors([
                'contact_phone' => 'Verify your phone number or email with the code before sending.',
            ]);
        }

        $period = $data['preferred_period'];
        $date = $data['preferred_date'];
        $dateLabel = $date;
        foreach ($availability['dates'] as $row) {
            if (($row['date'] ?? '') === $date) {
                $dateLabel = (string) ($row['label'] ?? $date);
                break;
            }
        }
        $preferredLabel = PublicAppointmentRequest::preferredLabel($dateLabel, $period);

        $this->leads->recordWebsiteSubmission([
            'source' => LeadSource::Website,
            'concern' => PublicAppointmentRequest::composeConcern($concern, $preferredLabel),
            'contact_phone' => PhoneNumber::normalize($data['contact_phone']) ?? $data['contact_phone'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_preference' => $preference,
            'vehicle_year' => $vehicle['year'] ?? ($data['vehicle_year'] ?? null),
            'vehicle_make' => $vehicle['make'] ?? ($data['vehicle_make'] ?? null),
            'vehicle_model' => $vehicle['model'] ?? ($data['vehicle_model'] ?? null),
            'vehicle_id' => $vehicle['id'] ?? null,
            'customer_id' => $vehicle['customer_id'] ?? null,
            'metadata' => PublicAppointmentRequest::metadataWithAvailability([
                'public_page' => 'book',
                'public_host' => $request->getHost(),
                'canonical_host' => $website->canonicalHost(),
            ], $date, $period, $preferredLabel),
        ]);

        return redirect()->route('public.leads.thanks');
    }

    private function sendPhoneCode(Request $request): RedirectResponse
    {
        if (! $this->phones->bookIdentityGateReady()) {
            return back()->withInput()->withErrors([
                'contact_phone' => 'Phone verification is not available right now. Call or text the shop.',
            ]);
        }

        $data = $request->validate([
            'contact_phone' => ['required', 'string', 'max:32', new ValidUsPhone],
        ]);

        try {
            $this->phoneAuthority->issue($data['contact_phone'], $request->ip(), $request->userAgent());
        } catch (PhoneVerificationException $exception) {
            return back()->withInput()->withErrors(['contact_phone' => $exception->getMessage()]);
        }

        return back()->withInput()->with('book_status', 'Verification code sent.');
    }

    private function checkPhoneCode(Request $request): RedirectResponse
    {
        if (! $this->phones->bookIdentityGateReady()) {
            return back()->withInput()->withErrors([
                'contact_phone' => 'Phone verification is not available right now. Call or text the shop.',
            ]);
        }

        $data = $request->validate([
            'contact_phone' => ['required', 'string', 'max:32', new ValidUsPhone],
            'phone_code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        try {
            $this->phoneAuthority->verify($request->session(), $data['contact_phone'], $data['phone_code']);
        } catch (PhoneVerificationException $exception) {
            return back()->withInput()->withErrors(['phone_code' => $exception->getMessage()]);
        }

        $this->recognizeVerifiedContact($request, $data['contact_phone']);

        return back()->withInput()->with('book_status', 'Phone verified.');
    }

    private function sendEmailCode(Request $request): RedirectResponse
    {
        if (! $this->emails->ready()) {
            return back()->withInput()->withErrors([
                'contact_email' => 'Email verification is not available right now.',
            ]);
        }

        $data = $request->validate([
            'contact_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $this->emailAuthority->issue($data['contact_email'], $request->ip(), $request->userAgent());
        } catch (EmailVerificationException $exception) {
            return back()->withInput()->withErrors(['contact_email' => $exception->getMessage()]);
        }

        return back()->withInput()->with('book_status', 'Verification code sent.');
    }

    private function checkEmailCode(Request $request): RedirectResponse
    {
        if (! $this->emails->ready()) {
            return back()->withInput()->withErrors([
                'contact_email' => 'Email verification is not available right now.',
            ]);
        }

        $data = $request->validate([
            'contact_email' => ['required', 'email', 'max:255'],
            'email_code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $this->emailAuthority->verify($request->session(), $data['contact_email'], $data['email_code']);
        } catch (EmailVerificationException $exception) {
            return back()->withInput()->withErrors(['email_code' => $exception->getMessage()]);
        }

        $this->recognizeVerifiedContact($request, $data['contact_email']);

        return back()->withInput()->with('book_status', 'Email verified.');
    }

    private function recognizeVerifiedContact(Request $request, string $contact): void
    {
        $resolved = app(ResolveCustomerByContact::class)->resolve($contact);

        if ($resolved !== null) {
            $request->session()->forget(CustomerRecognitionProjection::SESSION_GUEST_KEY);
            Auth::guard('portal')->login($resolved['customer']);
            $request->session()->regenerate();
            PortalObservationSession::start($request);

            return;
        }

        $request->session()->put(CustomerRecognitionProjection::SESSION_GUEST_KEY, true);
    }

    private function identityAccepted(Request $request, string $phone, ?string $email): bool
    {
        $customer = Auth::guard('portal')->user();
        if ($customer instanceof Customer) {
            $customerPhone = PhoneNumber::normalize((string) ($customer->phone ?? ''));
            $submitted = PhoneNumber::normalize($phone);
            if ($customerPhone !== null && $submitted !== null && $customerPhone === $submitted) {
                return true;
            }
        }

        $phoneReady = $this->phones->bookIdentityGateReady();
        $emailReady = $this->emails->ready();
        if (! $phoneReady && ! $emailReady) {
            return true;
        }

        if ($phoneReady && $this->phones->consumeBookProof($request->session(), $phone)) {
            return true;
        }

        return $emailReady && filled($email) && $this->emails->consumeBookProof($request->session(), $email);
    }

    /**
     * @return array{id: int, customer_id: int, year: int|null, make: string, model: string}|null
     */
    private function selectedVehicle(string $selection): ?array
    {
        if ($selection === '' || $selection === 'other') {
            return null;
        }

        $customer = Auth::guard('portal')->user();
        if (! $customer instanceof Customer || ! ctype_digit($selection)) {
            return null;
        }

        foreach ($this->recognition->forCustomer($customer)['vehicles'] as $vehicle) {
            if ((int) $vehicle['id'] !== (int) $selection) {
                continue;
            }

            return [
                'id' => (int) $vehicle['id'],
                'customer_id' => (int) $customer->id,
                'year' => $vehicle['year'],
                'make' => $vehicle['make'],
                'model' => $vehicle['model'],
            ];
        }

        return null;
    }
}
