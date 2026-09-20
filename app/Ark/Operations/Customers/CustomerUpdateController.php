<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
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
        EstimateTotalsCalculator $totalsCalculator,
    ): RedirectResponse|JsonResponse {
        $customerTypes = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->all();

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
            'customer_type' => ['required', Rule::in($customerTypes)],
            'notes' => ['nullable', 'string'],
            'messenger_psid' => ['nullable', 'string', 'max:64', Rule::unique('customers', 'messenger_psid')->ignore($customer->id)],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        $data['last_name'] = LeadContactNameParser::normalizeLastName($data['last_name'] ?? null);

        $previousCustomerType = $customer->customer_type;

        $customer->update(collect($data)->except('repair_order_id')->all());
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

        if ($previousCustomerType !== $customer->fresh()->customer_type) {
            $customer->repairOrders()
                ->where('status', '!=', RepairOrderStatus::Closed->value)
                ->with(['lines', 'customer', 'concerns'])
                ->each(fn ($repairOrder) => $totalsCalculator->recalculateRepairOrder($repairOrder));
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
