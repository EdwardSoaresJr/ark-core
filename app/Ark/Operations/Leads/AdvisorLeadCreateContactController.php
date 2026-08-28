<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvisorLeadCreateContactController
{
    public function create(Lead $lead, IngressCreateContactFormProjection $form): View
    {
        return view('operations.leads.create-contact', $form->forLead($lead));
    }

    public function store(
        Request $request,
        Lead $lead,
        CreateCustomerFromIngressContactAction $action,
    ): RedirectResponse {
        abort_if($lead->customer_id !== null, 409, 'Lead already has a customer.');
        abort_unless($lead->isOpen(), 404);
        abort_if(blank($lead->contact_phone), 422, 'Lead has no phone number to attach.');

        $customer = $action->execute(
            $this->validatedCustomerData($request),
            lead: $lead,
            conversation: $lead->conversation,
        );

        $status = $customer->wasRecentlyCreated
            ? 'Contact created · '.$customer->name
            : 'Linked to existing customer · '.$customer->name;

        return redirect()
            ->to(\App\Ark\Operations\Communications\CommunicationsNeedsYou::url(
                $lead->conversation_id !== null ? ['conversation' => $lead->conversation_id] : []
            ))
            ->with('status', $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCustomerData(Request $request): array
    {
        if ($request->input('contact_preference') === '') {
            $request->merge(['contact_preference' => null]);
        }

        $customerTypes = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->all();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_preference' => ['nullable', \Illuminate\Validation\Rule::enum(LeadContactPreference::class)],
            'referral_source' => ['nullable', \Illuminate\Validation\Rule::enum(EncounterSource::class)],
            'customer_type' => ['nullable', \Illuminate\Validation\Rule::in($customerTypes)],
        ]);

        $data['customer_type'] ??= $customerTypes[0] ?? 'Retail';
        $data['last_name'] = LeadContactNameParser::normalizeLastName($data['last_name'] ?? null);

        return $data;
    }
}
