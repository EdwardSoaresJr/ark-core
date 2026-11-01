@php
    use App\Ark\Operations\Portal\RepairOrderPortalActivity;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $displayTz = ShopDisplayTimezone::resolve();
    $portalActivityService = app(RepairOrderPortalActivity::class);
    $portalActivity = $portalActivityService->forRepairOrder($repairOrder);
@endphp

<div id="portal-rail" data-portal-panel>
    <div class="card-header">
        <div>
            <h3 class="card-title">Customer Portal</h3>
            <p class="card-subtitle">Copy customer links, preview estimate, inspection, and payment portals, and review real portal engagement for this repair order.</p>
        </div>
    </div>

    @include('operations.repair-orders.partials.estimate-portal-link', [
        'repairOrder' => $repairOrder,
        'isTerminal' => $isTerminal ?? false,
    ])

    @include('operations.repair-orders.partials.inspection-portal-link', [
        'repairOrder' => $repairOrder,
        'isTerminal' => $isTerminal ?? false,
    ])

    @include('operations.repair-orders.partials.payment-portal-link', [
        'repairOrder' => $repairOrder,
        'isTerminal' => $isTerminal ?? false,
    ])

    <section class="card-body" data-portal-activity>
        <div class="subheader">Portal activity</div>
        <p class="small text-secondary mb-2">Vehicle records, documents, and portal navigation - not estimate sends or estimate link opens.</p>
        <div class="list-group">
            @forelse ($portalActivity as $event)
                <div class="list-group-item">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                        <span class="fw-medium">{{ $portalActivityService->label($event) }}</span>
                        <span class="small text-secondary">
                            {{ $event->occurred_at?->timezone($displayTz)->format('M j, g:i A') }}
                        </span>
                    </div>
                    <div class="small text-secondary">{{ $portalActivityService->summary($event) }}</div>
                </div>
            @empty
                <div class="list-group-item small text-secondary">No customer portal engagement recorded for this repair order yet.</div>
            @endforelse
        </div>
    </section>
</div>
