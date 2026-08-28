<div class="divide-y divide-slate-100">
    @forelse ($customer->repairOrders as $repairOrder)
        <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="block px-3 py-2 hover:bg-slate-50">
            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <p class="text-sm font-bold text-slate-950">RO #{{ $repairOrder->repair_order_id }}</p>
                        <p class="truncate text-xs font-semibold text-slate-600">{{ $repairOrder->vehicle->display_name }}</p>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $repairOrder->concern_summary }}</p>
                    @if ($repairOrder->futureWorkCount() > 0)
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                            {{ $repairOrder->futureWorkSummary() }} · {{ $repairOrder->futureWorkNextAction() }}
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <span class="ops-state-pill">{{ $repairOrder->statusDisplayLabel() }}</span>
                    <span class="text-xs font-semibold text-slate-400">{{ $repairOrder->updated_at->timezone(config('app.display_timezone'))->format('M j, g:i A') }}</span>
                </div>
            </div>
        </a>
    @empty
        <div class="px-5 py-8 text-sm text-slate-600">No repair orders yet.</div>
    @endforelse
</div>
