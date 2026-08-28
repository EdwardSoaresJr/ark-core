@props([
    'breakdown',
    'variant' => 'portal',
])

@php
    $isPdf = $variant === 'pdf';
    $isStaff = $variant === 'staff';
@endphp

@if (filled($breakdown['labor'] ?? null))
    @if ($isStaff)
        <div class="ops-total-row py-1.5"><dt class="text-slate-500">Labor</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['labor'] }}</dd></div>
    @elseif ($isPdf)
        <div class="totals-row"><span>Labor</span><strong>{{ $breakdown['labor'] }}</strong></div>
    @else
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-600">Labor</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['labor'] }}</dd></div>
    @endif
@endif

@if (filled($breakdown['parts'] ?? null))
    @if ($isStaff)
        <div class="ops-total-row py-1.5"><dt class="text-slate-500">Parts</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['parts'] }}</dd></div>
    @elseif ($isPdf)
        <div class="totals-row"><span>Parts</span><strong>{{ $breakdown['parts'] }}</strong></div>
    @else
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-600">Parts</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['parts'] }}</dd></div>
    @endif
@endif

@if (($breakdown['standing_discount_cents'] ?? 0) > 0)
    @if ($isStaff)
        <div class="ops-total-row py-1.5"><dt class="text-slate-500">{{ $breakdown['standing_discount_label'] ?? 'Discount' }}</dt><dd class="font-semibold tabular-nums text-emerald-700">−{{ $breakdown['standing_discount'] }}</dd></div>
    @elseif ($isPdf)
        <div class="totals-row totals-row--credit"><span>{{ $breakdown['standing_discount_label'] ?? 'Discount' }}</span><strong>−{{ $breakdown['standing_discount'] }}</strong></div>
    @else
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-600">{{ $breakdown['standing_discount_label'] ?? 'Discount' }}</dt><dd class="font-semibold tabular-nums text-emerald-700">−{{ $breakdown['standing_discount'] }}</dd></div>
    @endif
@endif

@if (($breakdown['fees_cents'] ?? 0) > 0)
    @if ($isStaff)
        <div class="ops-total-row py-1.5"><dt class="text-slate-500">Fees</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['fees'] }}</dd></div>
    @elseif ($isPdf)
        <div class="totals-row"><span>Fees</span><strong>{{ $breakdown['fees'] }}</strong></div>
    @else
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-600">Fees</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['fees'] }}</dd></div>
    @endif
@endif

@if (($breakdown['show_subtotal_before_tax'] ?? false) && filled($breakdown['subtotal_before_tax'] ?? null))
    @if ($isStaff)
        <div class="ops-total-row py-1.5"><dt class="text-slate-600">Subtotal</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['subtotal_before_tax'] }}</dd></div>
    @elseif ($isPdf)
        <div class="totals-row"><span>Subtotal</span><strong>{{ $breakdown['subtotal_before_tax'] }}</strong></div>
    @else
        <div class="flex items-center justify-between gap-3"><dt class="font-medium text-slate-700">Subtotal</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['subtotal_before_tax'] }}</dd></div>
    @endif
@endif

@if (($breakdown['tax_cents'] ?? 0) > 0 && filled($breakdown['tax'] ?? null))
    @if ($isStaff)
        <div class="ops-total-row py-1.5"><dt class="text-slate-500">{{ $breakdown['customer_tax_label'] ?? 'Tax' }}</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['tax'] }}</dd></div>
    @elseif ($isPdf)
        <div class="totals-row"><span>{{ $breakdown['customer_tax_label'] ?? 'Tax' }}</span><strong>{{ $breakdown['tax'] }}</strong></div>
    @else
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-600">{{ $breakdown['customer_tax_label'] ?? 'Tax' }}</dt><dd class="font-semibold tabular-nums text-slate-950">{{ $breakdown['tax'] }}</dd></div>
    @endif
@endif

@php
    $finalEmphasis = (bool) ($breakdown['final_emphasis'] ?? true);
@endphp

@if ($isStaff)
    <div class="ops-total-row ops-total-row--final py-2"><dt>{{ $breakdown['total_label'] ?? 'Total' }}</dt><dd class="font-bold tabular-nums text-slate-950">{{ $breakdown['total'] ?? '—' }}</dd></div>
@elseif ($isPdf)
    <div @class([
        'totals-row',
        'final' => $finalEmphasis && ! ($breakdown['is_invoice'] ?? false),
        'totals-row--quiet-final' => ! $finalEmphasis && ! ($breakdown['is_invoice'] ?? false),
    ])><span>{{ $breakdown['total_label'] ?? 'Total' }}</span><span>{{ $breakdown['total'] ?? '—' }}</span></div>
@else
    <div @class([
        'portal-estimate-summary__total-row flex items-center justify-between gap-3',
        'rounded-lg border border-[#0099cc]/20 bg-[#0099cc]/5 px-3 py-3' => $finalEmphasis,
        'border-t border-slate-200 pt-2.5 mt-1' => ! $finalEmphasis,
    ])>
        <dt @class([
            'text-sm font-semibold text-slate-800' => $finalEmphasis,
            'text-sm text-slate-600' => ! $finalEmphasis,
        ])>{{ $breakdown['total_label'] ?? 'Total' }}</dt>
        <dd @class([
            'text-2xl font-black tabular-nums tracking-tight text-slate-950' => $finalEmphasis,
            'text-base font-semibold tabular-nums text-slate-950' => ! $finalEmphasis,
        ])>{{ $breakdown['total'] ?? '—' }}</dd>
    </div>
@endif
