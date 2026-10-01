<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;

final class IngressCreateContactFormProjection
{
    /**
     * @return array<string, mixed>
     */
    public function forLead(Lead $lead): array
    {
        abort_if($lead->customer_id !== null, 409, 'Lead already has a customer.');
        abort_unless($lead->isOpen(), 404);
        abort_if(blank($lead->contact_phone), 422, 'Lead has no phone number to attach.');

        $names = LeadContactNameParser::split($lead->contact_name);

        return $this->base(
            contextTitle: 'Create contact from '.$lead->source->label().' lead',
            contextDetail: PhoneNumber::display((string) $lead->contact_phone) ?? (string) $lead->contact_phone,
            formAction: route('operations.leads.create-contact.store', $lead),
            cancelUrl: \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(),
            firstName: $names['first_name'],
            lastName: $names['last_name'],
            phone: (string) $lead->contact_phone,
            email: $lead->contact_email,
            contactPreference: $lead->contact_preference ?? $this->defaultPreferenceForSource($lead->source),
            referralSource: $this->defaultReferralForSource($lead->source),
            lastNameOptional: LeadContactNameParser::allowsOptionalLastName($lead),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forPhone(
        string $phone,
        ?int $conversationId = null,
        ?int $callSessionId = null,
        ?LeadSource $sourceHint = null,
    ): array {
        $normalized = PhoneNumber::normalize($phone);

        abort_if($normalized === null, 422, 'A valid phone number is required.');

        $sourceHint ??= $callSessionId !== null ? LeadSource::Call : LeadSource::Sms;

        return $this->base(
            contextTitle: 'Create contact from '.$sourceHint->label(),
            contextDetail: PhoneNumber::display($normalized) ?? $normalized,
            formAction: route('operations.ingress.create-contact.store'),
            cancelUrl: url()->previous() !== url()->current()
                ? url()->previous()
                : route('operations.index'),
            firstName: '',
            lastName: '',
            phone: $normalized,
            email: null,
            contactPreference: $this->defaultPreferenceForSource($sourceHint),
            referralSource: $this->defaultReferralForSource($sourceHint),
            conversationId: $conversationId,
            callSessionId: $callSessionId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function base(
        string $contextTitle,
        string $contextDetail,
        string $formAction,
        string $cancelUrl,
        string $firstName,
        string $lastName,
        string $phone,
        ?string $email,
        ?LeadContactPreference $contactPreference,
        ?EncounterSource $referralSource,
        ?int $conversationId = null,
        ?int $callSessionId = null,
        bool $lastNameOptional = false,
    ): array {
        $customerTypes = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->all();

        return [
            'contextTitle' => $contextTitle,
            'contextDetail' => $contextDetail,
            'formAction' => $formAction,
            'cancelUrl' => $cancelUrl,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phone' => $phone,
            'displayPhone' => PhoneNumber::display($phone) ?? $phone,
            'email' => $email,
            'contactPreference' => $contactPreference,
            'referralSource' => $referralSource,
            'customerTypes' => $customerTypes,
            'defaultCustomerType' => $customerTypes[0] ?? 'Retail',
            'conversationId' => $conversationId,
            'callSessionId' => $callSessionId,
            'lastNameOptional' => $lastNameOptional,
        ];
    }

    private function defaultPreferenceForSource(LeadSource $source): LeadContactPreference
    {
        return match ($source) {
            LeadSource::Call => LeadContactPreference::Call,
            LeadSource::Website, LeadSource::Sms, LeadSource::Messenger => LeadContactPreference::Text,
            default => LeadContactPreference::Text,
        };
    }

    private function defaultReferralForSource(LeadSource $source): EncounterSource
    {
        return match ($source) {
            LeadSource::Call => EncounterSource::Phone,
            LeadSource::Sms => EncounterSource::Sms,
            LeadSource::Website => EncounterSource::Website,
            default => EncounterSource::Phone,
        };
    }

    public function resolveCallSession(?int $callSessionId): ?CallSession
    {
        if ($callSessionId === null) {
            return null;
        }

        return CallSession::query()->find($callSessionId);
    }
}
