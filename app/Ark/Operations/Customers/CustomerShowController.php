<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Documents\DocumentProjection;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Timeline\OperationalTimeline;
use App\Ark\Operations\Work\AdvisorWorkProjection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerShowController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        OperationalTimeline $timeline,
        CustomerCallContextResolver $callContextResolver,
        CustomerHubCommsTimeline $hubCommsTimeline,
        AdvisorWorkProjection $advisorWork,
        DocumentProjection $documents,
    ): View {
        $customer->load([
            'vehicles' => fn ($vehicles) => $vehicles
                ->withCount('repairOrders')
                ->with(['repairOrders' => fn ($repairOrders) => $repairOrders
                    ->with(['vehicle'])
                    ->latest()
                    ->limit(5)])
                ->latest(),
            'repairOrders' => fn ($repairOrders) => $repairOrders
                ->with(['vehicle', 'lines.concern'])
                ->latest()
                ->limit(10),
        ]);

        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        $activeRepairOrders = $customer->repairOrders->filter(
            fn ($repairOrder) => in_array($repairOrder->status->value, $activeStatuses, true),
        );

        $invoicedRepairOrders = RepairOrder::query()
            ->where('customer_id', $customer->id)
            ->whereIn('id', EstimateDocument::query()
                ->select('repair_order_id')
                ->where('document_type', FinancialDocumentType::Invoice->value)
                ->where('status', '!=', InvoiceStatus::Voided->value))
            ->get();
        $balances = app(BalanceDueCalculator::class)->mapForRepairOrders($invoicedRepairOrders);
        $creditDueRepairOrders = $invoicedRepairOrders
            ->filter(fn (RepairOrder $repairOrder): bool => $balances[$repairOrder->id]->hasOverpayment())
            ->map(fn (RepairOrder $repairOrder): array => [
                'repair_order' => $repairOrder,
                'credit_due_cents' => $balances[$repairOrder->id]->overpaidCents,
            ])
            ->values();

        $initialHubTab = match (true) {
            $request->query('compose') === 'text' => 'comms',
            in_array($request->query('comms'), ['call', 'text', 'email', 'logged'], true) => 'comms',
            in_array($request->query('tab'), ['work', 'vehicles', 'comms', 'visits', 'timeline', 'documents'], true) => (string) ($request->query('tab') === 'timeline' ? 'visits' : $request->query('tab')),
            $request->has('vehicle') => 'vehicles',
            $activeRepairOrders->isNotEmpty() => 'work',
            default => 'vehicles',
        };

        $hubInitialTask = null;
        $hubInitialContext = [];

        if (old('_vehicle_id')) {
            $hubInitialTask = 'hub-vehicle';
            $hubInitialContext = ['vehicleId' => (int) old('_vehicle_id')];
        }

        $showVehicleRail = $request->has('vehicle')
            || old('_vehicle_id') !== null
            || optional($request->session()->get('errors'))->hasAny([
                'vin', 'plate', 'plate_state', 'year', 'make', 'model', 'trim',
            ]);

        $callContext = $callContextResolver->resolveForCustomer($customer, messageLimit: 48);
        $commsLimit = $initialHubTab === 'comms' ? 60 : 36;
        $hubCommsTimelineRows = $hubCommsTimeline->buildForCustomer(
            $customer,
            $callContext->normalizedPhone !== '' ? $callContext->normalizedPhone : null,
            $commsLimit,
        );

        return view('operations.customers.show', [
            'customer' => $customer,
            'initialHubTab' => $initialHubTab,
            'hubInitialTask' => $hubInitialTask,
            'hubInitialContext' => $hubInitialContext,
            'showVehicleRail' => $showVehicleRail,
            'customerTypes' => ShopSettings::current()->customerTypeRows(),
            'activeStatuses' => $activeStatuses,
            'activeRepairOrders' => $activeRepairOrders,
            'creditDueRepairOrders' => $creditDueRepairOrders,
            'vehicleTimelineEntries' => $customer->vehicles
                ->mapWithKeys(fn ($vehicle): array => [
                    $vehicle->id => $timeline->forVehicleRepairOrders($vehicle->repairOrders, 5),
                ]),
            'callContext' => $callContext,
            'hubCommsTimeline' => $hubCommsTimelineRows,
            'hubCommsCounts' => $hubCommsTimeline->counts($hubCommsTimelineRows),
            'openFollowUps' => $advisorWork->openFollowUpsForCustomer($customer, $request->user()),
            'openTasks' => $advisorWork->openTasksForCustomer($customer, $request->user()),
            'operationalJourney' => null,
            'journeyComparison' => null,
            'operationalJourneyRepairOrder' => null,
            'customerDocuments' => $documents->forCustomer(
                $customer,
                $request->query('doc_type') ? (string) $request->query('doc_type') : null,
                $request->query('doc_q') ? (string) $request->query('doc_q') : null,
            ),
        ]);
    }
}
