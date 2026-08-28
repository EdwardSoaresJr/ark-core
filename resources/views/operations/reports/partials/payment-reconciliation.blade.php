@php
    $reconciliation = $paymentReconciliation;
    $postedSummary = $reconciliation['posted_ro_summary'];
@endphp

<div class="overflow-hidden border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Posted RO Summary</p>
        <p class="text-xs text-slate-400">ROs posted in this range.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <tbody class="divide-y divide-slate-100">
                <tr>
                    <td class="px-3 py-2 font-bold text-slate-950">Service sales (pre-tax)</td>
                    <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $postedSummary['service'] }}</td>
                </tr>
                <tr>
                    <td class="px-3 py-2 font-bold text-slate-950">Sales tax collected</td>
                    <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $postedSummary['tax'] }}</td>
                </tr>
                <tr class="bg-slate-50/80">
                    <td class="px-3 py-2 font-black text-slate-950">Posted invoice total</td>
                    <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $postedSummary['total'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @if (count($postedSummary['details']) > 0)
        <details class="border-t border-slate-200 px-3 py-2 text-sm">
            <summary class="cursor-pointer list-none font-semibold text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
                <span class="text-xs uppercase tracking-[0.06em] text-slate-500">{{ count($postedSummary['details']) }} posted RO{{ count($postedSummary['details']) === 1 ? '' : 's' }}</span>
            </summary>
            <div class="mt-2 divide-y divide-slate-100 border border-slate-200">
                @foreach ($postedSummary['details'] as $detail)
                    <a href="{{ route('operations.repair-orders.show', $detail['repair_order_pk']) }}" class="flex items-start justify-between gap-3 px-3 py-2 hover:bg-slate-50">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-950">#{{ $detail['repair_order_id'] }} · {{ $detail['customer'] }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $detail['vehicle'] }} · posted {{ $detail['posted_at'] }}</p>
                        </div>
                        <p class="shrink-0 font-black tabular-nums text-slate-950">{{ $detail['amount'] }}</p>
                    </a>
                @endforeach
            </div>
        </details>
    @endif
</div>

<div class="overflow-hidden border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Payments Reconciliation</p>
        <p class="text-xs text-slate-400">Bridge cash collected to posted sales.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                <tr>
                    <th class="px-3 py-2 font-bold">Line</th>
                    <th class="px-3 py-2 text-right font-bold">Amount</th>
                    <th class="px-3 py-2 font-bold">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($reconciliation['rows'] as $row)
                    <tr @class([
                        'bg-slate-50/80' => $row['emphasis'],
                    ])>
                        <td @class([
                            'px-3 py-2 font-bold text-slate-950',
                            'font-black' => $row['emphasis'],
                        ])>{{ $row['label'] }}</td>
                        <td @class([
                            'px-3 py-2 text-right tabular-nums text-slate-950',
                            'font-black' => $row['emphasis'],
                            'font-semibold' => ! $row['emphasis'],
                            'text-amber-800' => ($row['tone'] ?? null) === 'subtract' && $row['amount'] !== '$0.00' && $row['amount'] !== '−$0.00',
                        ])>{{ $row['amount'] }}</td>
                        <td class="px-3 py-2 text-xs leading-4 text-slate-500">{{ $row['note'] }}</td>
                    </tr>
                    @if (count($row['details']) > 0)
                        <tr>
                            <td colspan="3" class="bg-slate-50/40 px-3 py-2">
                                <details class="text-sm">
                                    <summary class="cursor-pointer list-none font-semibold text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
                                        <span class="text-xs uppercase tracking-[0.06em] text-slate-500">{{ count($row['details']) }} RO{{ count($row['details']) === 1 ? '' : 's' }}</span>
                                    </summary>
                                    <div class="mt-2 divide-y divide-slate-100 border border-slate-200 bg-white">
                                        @foreach ($row['details'] as $detail)
                                            <a href="{{ route('operations.repair-orders.show', $detail['repair_order_pk']) }}" class="flex items-start justify-between gap-3 px-3 py-2 hover:bg-slate-50">
                                                <div class="min-w-0">
                                                    <p class="truncate font-bold text-slate-950">#{{ $detail['repair_order_id'] }} · {{ $detail['customer'] }}</p>
                                                    <p class="mt-0.5 truncate text-xs text-slate-500">
                                                        {{ $detail['vehicle'] }}
                                                        · paid {{ $detail['payment_at'] }}
                                                        @if ($detail['posted_at'])
                                                            · posted {{ $detail['posted_at'] }}
                                                        @else
                                                            · not posted
                                                        @endif
                                                    </p>
                                                </div>
                                                <p class="shrink-0 font-black tabular-nums text-slate-950">{{ $detail['amount'] }}</p>
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    <div @class([
        'border-t px-3 py-2 text-xs',
        'border-emerald-200 bg-emerald-50 text-emerald-900' => $reconciliation['reconciles'],
        'border-amber-200 bg-amber-50 text-amber-950' => ! $reconciliation['reconciles'],
    ])>
        @if ($reconciliation['reconciles'])
            Reconciled cash matches posted invoice totals for this range.
        @else
            Reconciliation gap: {{ $reconciliation['delta_label'] }} vs posted invoice total — expand lines above to find the RO.
        @endif
    </div>
</div>
