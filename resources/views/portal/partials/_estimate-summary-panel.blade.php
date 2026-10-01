@props([
    'totalsBreakdown',
    'class' => '',
    'approvalForecast' => null,
])

<div {{ $attributes->merge(['class' => 'portal-estimate-summary '.$class]) }}>
    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Estimate summary</p>

    @include('operations.repair-orders.partials.repair-order-approval-forecast', [
        'approvalForecast' => $approvalForecast,
        'variant' => 'customer',
    ])

    @if (is_array($approvalForecast) && ($approvalForecast['visible'] ?? false))
        <p class="mt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Approved work breakdown</p>
    @endif

    <dl @class([
        'portal-estimate-summary__lines space-y-2.5 text-sm',
        'mt-4' => ! (is_array($approvalForecast) && ($approvalForecast['visible'] ?? false)),
        'mt-2' => is_array($approvalForecast) && ($approvalForecast['visible'] ?? false),
    ])>
        <x-operations.estimate-totals-breakdown :breakdown="$totalsBreakdown" variant="portal" />
    </dl>

    @if (filled($totalsBreakdown['discount_note'] ?? null))
        <p class="mt-3 text-xs leading-5 text-slate-500">{{ $totalsBreakdown['discount_note'] }}</p>
    @endif
</div>
