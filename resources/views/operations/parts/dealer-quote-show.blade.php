<x-operations.app :title="'Dealer Quote · '.($dealerQuote->quote_number ?: $dealerQuote->id)">
    <div class="mx-auto max-w-4xl space-y-4 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Dealer Quote</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-950">{{ $dealerQuote->sourceHeadline() }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    This estimate line originated from a captured dealer quote — not from catalog search.
                </p>
            </div>
            <a
                href="{{ route('operations.repair-orders.show', $repairOrder) }}#estimate-lines"
                class="ops-review-action"
            >
                Back to estimate
            </a>
        </div>

        <div class="grid gap-3 rounded-sm border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Supplier</p>
                <p class="font-semibold text-slate-900">{{ $dealerQuote->supplier_name ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Quote number</p>
                <p class="font-semibold text-slate-900">{{ $dealerQuote->quote_number ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Vehicle</p>
                <p class="font-semibold text-slate-900">{{ $dealerQuote->vehicle_description ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">VIN</p>
                <p class="font-semibold text-slate-900 break-all">{{ $dealerQuote->vin ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Captured by</p>
                <p class="font-semibold text-slate-900">{{ $dealerQuote->capturedBy?->name ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Captured at</p>
                <p class="font-semibold text-slate-900">{{ $dealerQuote->captured_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?: '—' }}</p>
            </div>
        </div>

        @if ($dealerQuote->hasOriginalDocument())
            <div>
                <a
                    href="{{ route('operations.repair-orders.dealer-quotes.download', [$repairOrder, $dealerQuote]) }}"
                    class="ops-review-action ops-review-action--primary"
                >
                    View Original Quote
                </a>
            </div>
        @endif

        <div class="overflow-hidden rounded-sm border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2">Part #</th>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2 text-right">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($dealerQuote->lines as $line)
                        <tr>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $line->quantity }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $line->part_number ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $line->description }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">${{ $line->unitCostDecimal() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-operations.app>
