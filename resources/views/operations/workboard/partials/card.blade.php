@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageCard $card */
    use App\Ark\Operations\Inspections\InspectionCoverageProjection;
    use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;

    $repairOrder = $card->repairOrder;
    $inspectionCoverage = InspectionCoverageProjection::for($repairOrder, auth()->user());
    $canRecordFinding = $inspectionCoverage['can_record'];
    $customerHubUrl = $repairOrder->customer_id !== null
        ? route('operations.customers.show', $repairOrder->customer_id)
        : null;
    $customerPhone = filled($repairOrder->customer?->display_phone)
        ? $repairOrder->customer->display_phone
        : null;
    $textUrl = $customerHubUrl !== null && filled($customerPhone)
        ? $customerHubUrl.'?compose=text#customer-communication'
        : null;
    $statusMoves = $repairOrder->isTerminal()
        ? []
        : RepairOrderLifecycleSelectProjection::forCatalogTargets($repairOrder, auth()->user())->boardMoves();
    $customerName = trim((string) ($repairOrder->customer?->name ?? ''));
@endphp

<article
    id="ops-card-ro-{{ $repairOrder->repair_order_id }}"
    class="ops-workboard-card-wrap ops-workboard-card-wrap--{{ $card->signalTone }}"
    @if ($card->countsAsNeedsAttention) data-workboard-attention="needs" @endif
>
    <div class="ops-workboard-card ops-workboard-card--{{ $card->signalTone }}">
        <div class="ops-workboard-card__top">
            <a href="{{ $card->href }}#builder" class="ops-workboard-card__ro">RO #{{ $repairOrder->repair_order_id }}</a>
            <x-operations.lifecycle-status-menu
                :repair-order="$repairOrder"
                :label="$repairOrder->statusDisplayLabel()"
                :tone="RepairOrderLifecycleSelectProjection::statusTone($repairOrder)"
                :status-moves="$statusMoves"
                :confirm-base-url="$card->href"
            />
        </div>

        <p class="ops-workboard-card__identity">
            @if ($customerHubUrl)
                <a href="{{ $customerHubUrl }}" class="ops-workboard-card__customer">{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</a>
            @else
                <span class="ops-workboard-card__customer">{{ $customerName !== '' ? $customerName : 'Unknown customer' }}</span>
            @endif
            @if ($textUrl)
                <a href="{{ $textUrl }}" class="ops-workboard-card__phone" title="Open communications">{{ $customerPhone }}</a>
            @elseif (filled($customerPhone))
                <span class="ops-workboard-card__phone">{{ $customerPhone }}</span>
            @endif
        </p>

        <a href="{{ $card->href }}#builder" class="ops-workboard-card__vehicle">{{ $card->vehicleLabel }}</a>

        @if ($card->signalLabel)
            <p class="ops-workboard-card__signal">
                @if ($card->signalTone === 'alert' || $card->signalTone === 'warn')
                    <span aria-hidden="true">⚠</span>
                @endif
                {{ $card->signalLabel }}
            </p>
        @endif

        <p class="ops-workboard-card__concern">{{ $card->concernHeadline }}</p>
        <p class="ops-workboard-card__age">{{ $card->ageLabel }}</p>
        @php
            $boardAppointment = \App\Ark\Operations\OperationsFeatures::appointmentsEnabled()
                ? app(\App\Ark\Operations\Appointments\ArrivalPostureProjection::class)->forRepairOrder($repairOrder)
                : null;
        @endphp
        @if ($boardAppointment?->present && filled($boardAppointment->whenLabel))
            <p class="ops-workboard-card__age text-slate-500">{{ $boardAppointment->headline }} · {{ $boardAppointment->whenLabel }}</p>
        @endif
    </div>

    @if ($canRecordFinding && ! $repairOrder->isTerminal())
        <div class="ops-workboard-card__actions">
            <a
                href="{{ $inspectionCoverage['capture_url'] }}"
                class="ops-workboard-card__finding-link"
                data-inspection-capture-cta
                data-capture-surface="{{ $inspectionCoverage['capture_surface'] }}"
                data-desktop-walk-url="{{ $inspectionCoverage['walk_url'] }}"
                data-tablet-url="{{ $inspectionCoverage['tablet_url'] }}"
            >{{ $inspectionCoverage['cta_label'] }}</a>
        </div>
    @endif
</article>
