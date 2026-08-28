@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrder $repairOrder */
    /** @var \App\Ark\Operations\Intake\IntakeQualificationProjection $qualification */
    $age = str_replace(' ago', '', $repairOrder->updated_at->diffForHumans(short: true, parts: 1));
    $ageMinutes = $repairOrder->updated_at->diffInMinutes();
    $agePressure = $ageMinutes >= 240 ? 'hot' : ($ageMinutes >= 60 ? 'warm' : 'fresh');
    $customerHubUrl = $repairOrder->customer_id !== null
        ? route('operations.customers.show', $repairOrder->customer_id)
        : null;
    $customerPhone = filled($repairOrder->customer?->display_phone)
        ? $repairOrder->customer->display_phone
        : null;
    $textUrl = $customerHubUrl !== null && filled($customerPhone)
        ? $customerHubUrl.'?compose=text#customer-communication'
        : null;
    $roUrl = route('operations.repair-orders.show', $repairOrder);
    $statusMoves = $repairOrder->isTerminal()
        ? []
        : \App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection::forCatalogTargets($repairOrder, auth()->user())->boardMoves();
@endphp

<article @class([
    'ops-queue-card',
    'ops-queue-card--qual',
    $qualification->isReady ? 'ops-queue-card--ready' : 'ops-queue-card--blocked',
])>
    <div class="ops-intake-qual-head">
        <div class="min-w-0">
            <a href="{{ $qualification->workspaceUrl }}" class="ops-intake-qual-vehicle">{{ $repairOrder->vehicle->display_name }}</a>
            <p class="ops-intake-qual-meta">
                @if ($customerHubUrl)
                    <a href="{{ $customerHubUrl }}" class="ops-page-link">{{ $repairOrder->customer->name }}</a>
                @else
                    <span>{{ $repairOrder->customer->name }}</span>
                @endif
                <span class="ops-intake-qual-sep">·</span>
                <a href="{{ $roUrl }}#builder" class="ops-page-link">#{{ $repairOrder->repair_order_id }}</a>
                @if ($textUrl)
                    <span class="ops-intake-qual-sep">·</span>
                    <a href="{{ $textUrl }}" class="ops-page-link" title="Open communications">{{ $customerPhone }}</a>
                @endif
            </p>
        </div>
        <div class="ops-intake-qual-head-actions">
            <x-operations.lifecycle-status-menu
                :repair-order="$repairOrder"
                :label="$repairOrder->statusDisplayLabel()"
                :tone="\App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection::statusTone($repairOrder)"
                :status-moves="$statusMoves"
                :confirm-base-url="$roUrl"
            />
            <span class="ops-intake-qual-score" title="Qualification completeness">{{ $qualification->qualificationLabel() }}</span>
        </div>
    </div>

    <a href="{{ $qualification->workspaceUrl }}" class="ops-intake-qual-body-link">
        <p class="ops-intake-qual-concern">{{ $qualification->concernPreview }}</p>

        @if ($qualification->missingLabels !== [])
            <div class="ops-intake-qual-missing">
                <p class="ops-intake-qual-missing-label">Missing</p>
                <ul class="ops-intake-qual-missing-list">
                    @foreach ($qualification->missingLabels as $missingLabel)
                        <li>{{ $missingLabel }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="ops-intake-qual-ready-note">Qualification complete — ready to convert.</p>
        @endif

        <div class="ops-intake-qual-footer">
            <span class="ops-intake-qual-next">{{ $qualification->nextAction }}</span>
            <span class="ops-ro-age ops-ro-age--{{ $agePressure }}">{{ $age }}</span>
        </div>
    </a>
</article>
