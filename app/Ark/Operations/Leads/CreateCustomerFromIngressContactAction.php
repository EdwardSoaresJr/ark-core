<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;

final class CreateCustomerFromIngressContactAction
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationLinker $conversationLinker,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     email?: string|null,
     *     contact_preference?: LeadContactPreference|null,
     *     referral_source?: EncounterSource|null,
     *     customer_type?: string|null,
     * }  $data
     */
    public function execute(
        array $data,
        ?Lead $lead = null,
        ?Conversation $conversation = null,
        ?CallSession $callSession = null,
    ): Customer {
        $phone = PhoneNumber::normalize($data['phone']);

        if ($phone === null) {
            abort(422, 'A valid phone number is required to create a contact.');
        }

        $existing = $this->callContextResolver->resolve($phone)?->customer;

        if ($existing instanceof Customer) {
            $this->attach($existing, $phone, $lead, $conversation, $callSession);

            return $existing;
        }

        $customer = Customer::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => LeadContactNameParser::normalizeLastName($data['last_name'] ?? null),
            'phone' => $phone,
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            'contact_preference' => $data['contact_preference'] ?? null,
            'referral_source' => $data['referral_source'] ?? null,
            'customer_type' => $data['customer_type'] ?? 'Retail',
        ]);

        $this->attach($customer, $phone, $lead, $conversation, $callSession);

        return $customer;
    }

    private function attach(
        Customer $customer,
        string $phone,
        ?Lead $lead,
        ?Conversation $conversation,
        ?CallSession $callSession,
    ): void {
        if ($lead instanceof Lead && $lead->customer_id === null) {
            $lead->update([
                'customer_id' => $customer->id,
                'contact_name' => filled($lead->contact_name) && ! strcasecmp((string) $lead->contact_name, 'unknown')
                    ? $lead->contact_name
                    : $customer->name,
            ]);
        } else {
            $openLead = Lead::query()
                ->open()
                ->where('contact_phone', $phone)
                ->whereNull('customer_id')
                ->latest('id')
                ->first();

            if ($openLead instanceof Lead) {
                $openLead->update([
                    'customer_id' => $customer->id,
                    'contact_name' => filled($openLead->contact_name) && ! strcasecmp((string) $openLead->contact_name, 'unknown')
                        ? $openLead->contact_name
                        : $customer->name,
                ]);
            }
        }

        $conversation ??= $lead?->conversation;

        if ($conversation instanceof Conversation) {
            $this->conversationLinker->link($conversation, $customer);
        }

        if ($callSession instanceof CallSession && $callSession->customer_id === null) {
            $callSession->update(['customer_id' => $customer->id]);
        }
    }
}
