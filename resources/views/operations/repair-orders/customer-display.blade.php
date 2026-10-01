@php
    $title = ($display['vehicle_name'] ?? 'Estimate').' · '.$display['shop_name'];
@endphp

<x-layouts.operations-customer-display
    :title="$title"
    :refresh-seconds="$refreshSeconds"
    :fragment-url="route('operations.repair-orders.customer-display.fragment', $repairOrder)"
>
    <div class="flex h-full flex-col px-8 py-6 lg:px-10 lg:py-7">
        <header class="mb-6 flex shrink-0 items-end justify-between gap-6">
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $display['shop_name'] }}</p>
                <h1 class="mt-1 truncate text-4xl font-bold tracking-tight text-slate-950 lg:text-5xl">{{ $display['vehicle_name'] }}</h1>
                <p class="mt-2 text-lg text-slate-600">
                    @if (filled($display['customer_first_name']))
                        {{ $display['customer_first_name'] }}
                        <span class="text-slate-300">·</span>
                    @endif
                    RO #{{ $display['repair_order_number'] }}
                    <span class="text-slate-300">·</span>
                    {{ $display['status_label'] }}
                </p>
            </div>
        </header>

        <div id="ops-customer-display-board" class="flex min-h-0 flex-1 flex-col">
            @include('operations.repair-orders.partials.customer-display-board', ['display' => $display])
        </div>
    </div>
</x-layouts.operations-customer-display>
