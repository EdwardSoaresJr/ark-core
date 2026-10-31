<div class="list-group list-group-flush">
    @forelse ($customer->repairOrders as $repairOrder)
        <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="list-group-item list-group-item-action">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-sm">
                    <div class="d-flex flex-wrap align-items-baseline gap-2">
                        <span class="fw-bold">RO #{{ $repairOrder->repair_order_id }}</span>
                        <span class="small fw-medium text-secondary text-truncate">{{ $repairOrder->vehicle->display_name }}</span>
                    </div>
                    <div class="small text-secondary text-truncate">{{ $repairOrder->concern_summary }}</div>
                    @if ($repairOrder->futureWorkCount() > 0)
                        <div class="small fw-medium mt-1">
                            {{ $repairOrder->futureWorkSummary() }} · {{ $repairOrder->futureWorkNextAction() }}
                        </div>
                    @endif
                </div>
                <div class="col-12 col-sm-auto d-flex flex-wrap align-items-center gap-2">
                    <x-tabler.status-badge :tone="$repairOrder->status->indexTone()">{{ $repairOrder->statusDisplayLabel() }}</x-tabler.status-badge>
                    <span class="small text-secondary">{{ $repairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j, g:i A') }}</span>
                </div>
            </div>
        </a>
    @empty
        <div class="list-group-item text-secondary">No repair orders yet.</div>
    @endforelse
</div>
