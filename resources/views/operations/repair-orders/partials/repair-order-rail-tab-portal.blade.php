@php
    use App\Ark\Operations\Portal\RepairOrderPortalActivity;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $displayTz = ShopDisplayTimezone::resolve();
    $portalActivityService = app(RepairOrderPortalActivity::class);
    $portalActivity = $portalActivityService->forRepairOrder($repairOrder);
@endphp

<div id="portal-rail" class="ops-review-rail-tab-panel divide-y divide-slate-100 text-sm">
    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Customer Portal</p>
        <p class="ops-meta mt-0.5">Copy customer links, preview estimate, inspection, and payment portals, and review real portal engagement for this repair order.</p>
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

    <section class="px-3 py-2.5">
        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Portal activity</p>
        <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Vehicle records, documents, and portal navigation — not estimate sends or estimate link opens.</p>
        <div class="mt-2 divide-y divide-slate-100 border border-slate-200 bg-white">
            @forelse ($portalActivity as $event)
                <div class="px-3 py-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-950">{{ $portalActivityService->label($event) }}</p>
                        <p class="text-[11px] font-semibold text-slate-400">
                            {{ $event->occurred_at?->timezone($displayTz)->format('M j, g:i A') }}
                        </p>
                    </div>
                    <p class="mt-0.5 text-xs leading-4 text-slate-600">{{ $portalActivityService->summary($event) }}</p>
                </div>
            @empty
                <p class="px-3 py-2 text-xs leading-4 text-slate-500">No customer portal engagement recorded for this repair order yet.</p>
            @endforelse
        </div>
    </section>
</div>
