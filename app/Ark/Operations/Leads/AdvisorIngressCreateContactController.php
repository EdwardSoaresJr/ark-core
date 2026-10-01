<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvisorIngressCreateContactController
{
    public function create(Request $request, IngressCreateContactFormProjection $form): View
    {
        $phone = (string) $request->query('phone', '');
        $conversationId = $request->integer('conversation_id') ?: null;
        $callSessionId = $request->integer('call_session_id') ?: null;

        return view('operations.leads.create-contact', $form->forPhone(
            phone: $phone,
            conversationId: $conversationId,
            callSessionId: $callSessionId,
        ));
    }

    public function store(
        Request $request,
        CreateCustomerFromIngressContactAction $action,
        IngressCreateContactFormProjection $formProjection,
    ): RedirectResponse {
        $data = $this->validatedCustomerData($request);

        $conversationId = $request->integer('conversation_id') ?: null;
        $callSessionId = $request->integer('call_session_id') ?: null;

        $conversation = $conversationId !== null
            ? Conversation::query()->find($conversationId)
            : null;

        $callSession = $formProjection->resolveCallSession($callSessionId);

        $customer = $action->execute(
            $data,
            conversation: $conversation,
            callSession: $callSession,
        );

        $status = $customer->wasRecentlyCreated
            ? 'Contact created · '.$customer->name
            : 'Linked to existing customer · '.$customer->name;

        $redirect = $request->input('return_url');

        if (is_string($redirect) && $redirect !== '' && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect)->with('status', $status);
        }

        return redirect()
            ->route('operations.customers.show', $customer)
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
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['customer_type'] ??= $customerTypes[0] ?? 'Retail';
        $data['phone'] = PhoneNumber::normalize($data['phone']) ?? $data['phone'];
        $data['last_name'] = LeadContactNameParser::normalizeLastName($data['last_name'] ?? null);

        return $data;
    }
}
