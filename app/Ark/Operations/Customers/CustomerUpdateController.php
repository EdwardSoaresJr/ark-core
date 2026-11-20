<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\EstimatePricing\CanonicalBillingClassCatalog;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Financial\OverpaymentDisposition;
use App\Ark\Operations\Intake\IntakeWorkspaceSession;
use App\Ark\Operations\Leads\LeadContactNameParser;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\RepairOrders\RepairOrderIdentityJsonResponse;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerUpdateController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        EstimateDocumentService $documents,
        ApplyCustomerBillingClassChangeAction $applyBillingClassChange,
    ): RedirectResponse|JsonResponse {
        $catalog = collect(CanonicalBillingClassCatalog::selectableForCustomers(ShopSettings::current()));
        $customerTypes = $catalog->pluck('name')->all();
        $classKeys = $catalog->pluck('key')->all();
        if (filled($customer->customer_type) && ! in_array($customer->customer_type, $customerTypes, true)) {
            $customerTypes[] = (string) $customer->customer_type;
        }
        if (filled($customer->billingClassKey()) && ! in_array($customer->billingClassKey(), $classKeys, true)) {
            $classKeys[] = (string) $customer->billingClassKey();
        }

        if ($request->input('contact_preference') === '') {
            $request->merge(['contact_preference' => null]);
        }

        $intakeUpdate = $request->boolean('intake');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => [$intakeUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_preference' => ['nullable', Rule::enum(LeadContactPreference::class)],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'referral_source' => ['nullable', Rule::enum(EncounterSource::class)],
            // The class key is identity; the display name is accepted for older forms and API callers.
            'billing_class_key' => ['nullable', 'string', Rule::in($classKeys)],
            'customer_type' => ['required_without:billing_class_key', 'nullable', Rule::in($customerTypes)],
            'overpayment_disposition_default' => ['nullable', Rule::in(array_map(
                fn (OverpaymentDisposition $disposition): string => $disposition->value,
                OverpaymentDisposition::customerDefaults(),
            ))],
            'notes' => ['nullable', 'string'],
            'messenger_psid' => ['nullable', 'string', 'max:64', Rule::unique('customers', 'messenger_psid')->ignore($customer->id)],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        $data['last_name'] = LeadContactNameParser::normalizeLastName($data['last_name'] ?? null);

        $previousCustomerType = $customer->customer_type;
        $previousClassKey = $customer->billingClassKey();

        $attributes = collect($data)->except('repair_order_id')->all();
        if (filled($attributes['billing_class_key'] ?? null)) {
            // Name is derived from the key (Customer::saving) so the two can never disagree.
            unset($attributes['customer_type']);
        } else {
            unset($attributes['billing_class_key']);
        }

        $customer->update($attributes);
        $customer->refresh();

        $linkedPsid = trim((string) $customer->messenger_psid);

        if ($linkedPsid !== '') {
            $conversation = app(ConversationResolver::class)
                ->forContactKey(ConversationContactSurface::Messenger, $linkedPsid);
            app(ConversationLinker::class)->link($conversation, $customer);
        }

        $customer->repairOrders()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->each(fn ($repairOrder) => $documents->markDirtyForRepairOrder($repairOrder));

        $updated = $customer->fresh();
        if ($previousCustomerType !== $updated->customer_type || $previousClassKey !== $updated->billingClassKey()) {
            $applyBillingClassChange->execute($customer->fresh(), $previousCustomerType, $request->user());
        }

        $customer->refresh();

        if ($request->boolean('intake')) {
            return redirect()
                ->to(IntakeWorkspaceSession::routeFromRequestOrInput($request, [
                    'customer_id' => $customer->id,
                ]))
                ->with('status', 'Customer updated · '.$customer->name);
        }

        if ($json = RepairOrderIdentityJsonResponse::forCustomerUpdate($request, $customer, 'Customer updated.')) {
            return $json;
        }

        $repairOrder = RepairOrderIdentityJsonResponse::resolveRepairOrderForRedirect(
            $request->integer('repair_order_id'),
        );

        if ($repairOrder !== null) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->with('status', 'Customer updated.');
        }

        return redirect()
            ->route('operations.customers.show', $customer)
            ->with('status', 'Customer updated.');
    }
}
