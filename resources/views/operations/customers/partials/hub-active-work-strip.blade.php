@if ($callContext->openRepairOrders->isNotEmpty())
    <section id="open-repair-orders" class="card">
        <div class="card-status-top bg-orange"></div>
        <div class="card-header">
            <div>
                <h3 class="card-title">Active Work</h3>
                <div class="text-secondary small">{{ $callContext->openRepairOrders->count() }} open {{ Str::plural('repair order', $callContext->openRepairOrders->count()) }} need attention</div>
            </div>
            <div class="card-actions">
                <button type="button" class="btn btn-sm" x-on:click="selectTab('work')">View all</button>
            </div>
        </div>
        <div class="list-group list-group-flush">
            @foreach ($callContext->openRepairOrders->take(3) as $openRepairOrder)
                <a
                    href="{{ route('operations.repair-orders.show', $openRepairOrder->repairOrder) }}"
                    class="list-group-item list-group-item-action"
                >
                    <div class="d-flex flex-wrap align-items-baseline gap-1">
                        <span class="fw-bold">RO #{{ $openRepairOrder->repairOrder->repair_order_id }}</span>
                        <span class="text-secondary">·</span>
                        <span class="fw-medium">{{ $openRepairOrder->vehicle->display_name }}</span>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between gap-2 small text-secondary">
                        <span>{{ $openRepairOrder->repairOrder->status->label() }}</span>
                        <span class="fw-medium">{{ $openRepairOrder->workflowPostureLabel }}</span>
                    </div>
                </a>
            @endforeach
            @if ($callContext->openRepairOrders->count() > 3)
                <button
                    type="button"
                    class="list-group-item list-group-item-action fw-medium text-orange"
                    x-on:click="selectTab('work')"
                >
                    +{{ $callContext->openRepairOrders->count() - 3 }} more on Work tab
                </button>
            @endif
        </div>
    </section>
@endif
