@if ($callContext->openRepairOrders->isNotEmpty())
    <section id="open-repair-orders" class="border border-amber-200 bg-amber-50/60 px-3 py-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-800">Active Work</p>
                <p class="ops-meta mt-0.5">{{ $callContext->openRepairOrders->count() }} open {{ Str::plural('repair order', $callContext->openRepairOrders->count()) }} need attention</p>
            </div>
            <button
                type="button"
                @click="selectTab('work')"
                class="ops-page-link text-xs"
            >
                View all
            </button>
        </div>
        <div class="mt-2 grid gap-1.5">
            @foreach ($callContext->openRepairOrders->take(3) as $openRepairOrder)
                <a
                    href="{{ route('operations.repair-orders.show', $openRepairOrder->repairOrder) }}"
                    class="flex flex-wrap items-center justify-between gap-2 border border-amber-200/80 bg-white px-2.5 py-2 text-xs hover:border-amber-300"
                >
                    <div class="min-w-0">
                        <span class="font-black text-slate-950">RO #{{ $openRepairOrder->repairOrder->repair_order_id }}</span>
                        <span class="mx-1 text-slate-300">·</span>
                        <span class="font-semibold text-slate-700">{{ $openRepairOrder->vehicle->display_name }}</span>
                        <span class="mx-1 text-slate-300">·</span>
                        <span class="text-slate-500">{{ $openRepairOrder->repairOrder->status->label() }}</span>
                    </div>
                    <span class="shrink-0 font-semibold text-slate-500">{{ $openRepairOrder->workflowPostureLabel }}</span>
                </a>
            @endforeach
            @if ($callContext->openRepairOrders->count() > 3)
                <button
                    type="button"
                    @click="selectTab('work')"
                    class="px-1 py-0.5 text-left text-xs font-semibold text-amber-900 hover:text-amber-950"
                >
                    +{{ $callContext->openRepairOrders->count() - 3 }} more on Work tab
                </button>
            @endif
        </div>
    </section>
@endif
