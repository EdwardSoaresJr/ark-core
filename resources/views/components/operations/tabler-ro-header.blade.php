@props([
    'repairOrder',
    'totals',
    'estimateVersion' => null,
    'isTerminal' => false,
    'canAuthorRepairOrder' => false,
    'financial' => null,
    'customerDocumentsCount' => 0,
    'lifecycleOptions' => [],
    'closeVariantOptions' => [],
    'technicians' => null,
    'soloOwnerShop' => false,
    'balanceProjection' => null,
])

@php
    use App\Ark\Operations\RepairOrders\OperationalIdentityPresenter;
    use App\Ark\Operations\RepairOrders\RepairOrderStatus;
    use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
    use App\Ark\Runtime\Authorization\ArkCapability;

    $identity = OperationalIdentityPresenter::forRepairOrder($repairOrder);
    $customerLines = collect($identity['customer']['lines'] ?? []);
    $vehicleLines = collect($identity['vehicle']['lines'] ?? []);
    $phone = $customerLines->firstWhere('label', 'Phone');
    $vinLine = $vehicleLines->firstWhere('label', 'VIN');
    $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder)?->label();
    $status = $repairOrder->status;
    $statusBadge = match ($status) {
        RepairOrderStatus::WaitingParts, RepairOrderStatus::WaitingApproval => 'bg-yellow-lt text-yellow-lt-fg',
        RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork, RepairOrderStatus::ReadyPickup => 'bg-green-lt text-green-lt-fg',
        RepairOrderStatus::InProgress, RepairOrderStatus::QualityCheck => 'bg-azure-lt text-azure-lt-fg',
        RepairOrderStatus::Invoiced, RepairOrderStatus::Completed, RepairOrderStatus::Closed => 'bg-secondary-lt text-secondary-lt-fg',
        default => 'bg-blue-lt text-blue-lt-fg',
    };
    $mileageIn = $repairOrder->mileage_in !== null
        ? number_format((int) $repairOrder->mileage_in)
        : ($repairOrder->resolvedMileageIn() ? number_format((int) $repairOrder->resolvedMileageIn()) : null);
    $customerProfileHref = route('operations.customers.show', $repairOrder->customer);
    $scheduleFromRoHref = \App\Ark\Operations\OperationsFeatures::appointmentsEnabled()
        ? \App\Ark\Operations\Appointments\ScheduleUrl::to(['repair_order' => $repairOrder->id])
        : null;
    $newRoFromExistingHref = auth()->user()?->can(ArkCapability::RepairOrdersManage->value)
        ? route('operations.intake.create', array_filter([
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id ?: null,
            'source_repair_order_id' => $repairOrder->id,
        ]))
        : null;
@endphp

<div class="ark-tabler-ro__header-stack" x-data="{ identityOpen: false }">
    <div class="page-header ark-tabler-ro__page-header">
        <div class="ark-tabler-ro__page-header-top">
            <div class="ark-tabler-ro__page-header-lead">
                <div class="page-pretitle d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span>RO #{{ $repairOrder->repair_order_id }}</span>
                    <span class="ark-tabler-ro__status-quiet {{ $statusBadge }}">{{ $repairOrder->statusDisplayLabel() }}</span>
                    @if (filled($visitMode))
                        <span class="text-secondary">· {{ $visitMode }}</span>
                    @endif
                </div>
                <h2 class="page-title mb-0">
                    <button
                        type="button"
                        class="btn btn-link p-0 text-reset text-decoration-none fw-bold"
                        title="Edit vehicle"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'vehicle-identity', invokeEl: $event.currentTarget } }))"
                    >{{ $identity['vehicle']['title'] }}</button>
                </h2>
            </div>
            <div class="ark-tabler-ro__page-header-actions btn-list">
                <a href="{{ $customerProfileHref }}" class="btn btn-outline-primary btn-sm">Customer</a>
                <a href="#communication-rail" class="btn btn-outline-primary btn-sm">Message</a>
                @if ($scheduleFromRoHref)
                    <a href="{{ $scheduleFromRoHref }}" class="btn btn-outline-primary btn-sm">Schedule</a>
                @endif
                @if ($newRoFromExistingHref)
                    <a href="{{ $newRoFromExistingHref }}" class="btn btn-outline-primary btn-sm">New RO</a>
                @endif
                <button type="button" class="btn btn-ghost-secondary btn-sm" @click="identityOpen = !identityOpen" :aria-expanded="identityOpen">
                    <span x-text="identityOpen ? 'Hide details' : 'Details'"></span>
                </button>
            </div>
        </div>

        <div class="ark-tabler-ro__identity-meta" aria-label="Repair order identity">
            <div class="ark-tabler-ro__identity-meta-item">
                <span class="ark-tabler-ro__identity-meta-label">Customer</span>
                <button
                    type="button"
                    class="btn btn-link p-0 text-reset text-decoration-none ark-tabler-ro__identity-meta-value"
                    title="Edit customer"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'customer-identity', invokeEl: $event.currentTarget } }))"
                >{{ $identity['customer']['title'] }}</button>
            </div>
            @if (filled($phone['value'] ?? null))
                <div class="ark-tabler-ro__identity-meta-item">
                    <span class="ark-tabler-ro__identity-meta-label">Phone</span>
                    @if (! empty($phone['href']))
                        <a href="{{ $phone['href'] }}" class="ark-tabler-ro__identity-meta-value ark-tabler-ro__identity-meta-link">{{ $phone['value'] }}</a>
                    @else
                        <span class="ark-tabler-ro__identity-meta-value">{{ $phone['value'] }}</span>
                    @endif
                </div>
            @endif
            @if (filled($vinLine['value'] ?? null))
                <div class="ark-tabler-ro__identity-meta-item">
                    <span class="ark-tabler-ro__identity-meta-label">VIN</span>
                    <span class="ark-tabler-ro__identity-meta-value">{{ $vinLine['value'] }}</span>
                </div>
            @endif
            @if ($mileageIn)
                <div class="ark-tabler-ro__identity-meta-item">
                    <span class="ark-tabler-ro__identity-meta-label">Mileage</span>
                    <span class="ark-tabler-ro__identity-meta-value">{{ $mileageIn }} mi</span>
                </div>
            @endif
            <div class="ark-tabler-ro__identity-meta-item">
                <span class="ark-tabler-ro__identity-meta-label">Advisor</span>
                <span class="ark-tabler-ro__identity-meta-value">{{ $repairOrder->serviceAdvisorName() ?: 'No advisor' }}</span>
            </div>
            <div class="ark-tabler-ro__identity-meta-item">
                <span class="ark-tabler-ro__identity-meta-label">Technician</span>
                <span class="ark-tabler-ro__identity-meta-value">{{ $repairOrder->technicianOwnershipLabel() ?: 'Unassigned' }}</span>
            </div>
        </div>
    </div>

    <div class="card mb-3" id="review-toolbar">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2 ops-review-toolbar">
                @include('operations.repair-orders.partials.repair-order-toolbar-visit-signals', [
                    'repairOrder' => $repairOrder,
                ])

                @unless ($isTerminal)
                    @if ($canAuthorRepairOrder)
                        <button
                            type="button"
                            class="btn btn-sm"
                            @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-estimate-notes', context: {}, invokeEl: $event.currentTarget } }))"
                        >
                            Notes
                        </button>
                    @endif
                @endunless

                @include('operations.repair-orders.partials.repair-order-toolbar-print-slot', [
                    'repairOrder' => $repairOrder,
                    'financial' => $financial,
                    'customerDocumentsCount' => $customerDocumentsCount,
                    'canAuthorRepairOrder' => $canAuthorRepairOrder,
                    'isTerminal' => $isTerminal,
                ])

                @include('operations.repair-orders.partials.repair-order-estimate-toolbar-workflow', [
                    'repairOrder' => $repairOrder,
                    'isTerminal' => $isTerminal,
                    'lifecycleOptions' => $lifecycleOptions,
                    'closeVariantOptions' => $closeVariantOptions,
                    'technicians' => $technicians,
                    'soloOwnerShop' => $soloOwnerShop,
                    'estimateVersion' => $estimateVersion,
                    'financial' => $financial,
                    'balanceProjection' => $balanceProjection,
                    'mode' => 'edit',
                ])

                @include('operations.repair-orders.partials.repair-order-toolbar-mode-slot', [
                    'repairOrder' => $repairOrder,
                    'mode' => 'edit',
                    'isTerminal' => $isTerminal,
                    'registerModeShortcut' => false,
                ])
            </div>
        </div>
    </div>

    <div class="card mb-3" x-show="identityOpen" x-cloak>
        <div class="card-body">
            @include('operations.repair-orders.partials.operational-identity-band', [
                'repairOrder' => $repairOrder,
                'identityVariant' => 'staff',
                'estimateVersion' => $estimateVersion,
                'totals' => $totals,
            ])
        </div>
    </div>
</div>
