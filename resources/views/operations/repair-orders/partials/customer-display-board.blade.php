@php
    /** @var array<string, mixed> $display */
    $concerns = $display['concerns'] ?? [];
    $totals = $display['totals'] ?? [];
@endphp

<div class="grid min-h-0 flex-1 grid-cols-1 gap-8 overflow-hidden lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_26rem]">
    <div class="min-h-0 overflow-y-auto pr-1">
        @if (($display['general_photos'] ?? []) !== [])
            <div class="mb-6 flex gap-3 overflow-x-auto">
                @foreach ($display['general_photos'] as $photo)
                    <img src="{{ $photo['url'] }}" alt="{{ $photo['caption'] !== '' ? $photo['caption'] : 'Photo' }}" class="h-40 w-auto rounded-lg object-cover">
                @endforeach
            </div>
        @endif

        @forelse ($concerns as $concern)
            <article class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm last:mb-0">
                <div class="flex items-start justify-between gap-6">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ $concern['intent_label'] }}</p>
                        <h2 class="mt-1 text-2xl font-semibold leading-snug tracking-tight text-slate-950">{{ $concern['summary'] }}</h2>
                        @if (filled($concern['disposition_label']))
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ $concern['disposition_label'] }}</p>
                        @endif
                    </div>
                    @if (filled($concern['subtotal']))
                        <p class="shrink-0 text-2xl font-black tabular-nums text-slate-950">{{ $concern['subtotal'] }}</p>
                    @endif
                </div>

                @if (($concern['photos'] ?? []) !== [])
                    <div class="mt-4 flex gap-3 overflow-x-auto">
                        @foreach ($concern['photos'] as $photo)
                            <img src="{{ $photo['url'] }}" alt="{{ $photo['caption'] !== '' ? $photo['caption'] : 'Photo' }}" class="h-44 w-auto rounded-lg object-cover">
                        @endforeach
                    </div>
                @endif

                @if (($concern['lines'] ?? []) !== [])
                    <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                        @foreach ($concern['lines'] as $line)
                            <li class="flex items-baseline justify-between gap-4 py-2.5 text-lg">
                                <span class="min-w-0 text-slate-800">{{ $line['description'] }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-950">{{ $line['total'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white px-5 py-8 text-lg text-slate-600">
                Your advisor is preparing this estimate.
            </p>
        @endforelse
    </div>

    <aside class="flex min-h-0 flex-col justify-end rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-0">
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">{{ $display['total_label'] ?? 'Total' }}</p>
        <p class="mt-2 text-5xl font-black tabular-nums tracking-tight text-slate-950">{{ $totals['total'] ?? '' }}</p>

        <dl class="mt-6 space-y-2 text-base text-slate-700">
            @if (filled($totals['labor'] ?? null) && (int) ($totals['labor_cents'] ?? 0) > 0)
                <div class="flex justify-between gap-4"><dt>Labor</dt><dd class="tabular-nums font-semibold text-slate-950">{{ $totals['labor'] }}</dd></div>
            @endif
            @if (filled($totals['parts'] ?? null) && (int) ($totals['parts_cents'] ?? 0) > 0)
                <div class="flex justify-between gap-4"><dt>Parts</dt><dd class="tabular-nums font-semibold text-slate-950">{{ $totals['parts'] }}</dd></div>
            @endif
            @if ((int) ($totals['standing_discount_cents'] ?? 0) > 0)
                <div class="flex justify-between gap-4"><dt>{{ $totals['standing_discount_label'] ?? 'Discount' }}</dt><dd class="tabular-nums font-semibold text-slate-950">−{{ $totals['standing_discount'] }}</dd></div>
            @endif
            @if ((int) ($totals['fees_cents'] ?? 0) > 0)
                <div class="flex justify-between gap-4"><dt>Fees</dt><dd class="tabular-nums font-semibold text-slate-950">{{ $totals['fees'] }}</dd></div>
            @endif
            @if ((int) ($totals['tax_cents'] ?? 0) > 0)
                <div class="flex justify-between gap-4"><dt>{{ $totals['customer_tax_label'] ?? 'Tax' }}</dt><dd class="tabular-nums font-semibold text-slate-950">{{ $totals['tax'] }}</dd></div>
            @endif
        </dl>

        <p class="mt-8 text-sm leading-5 text-slate-500">Your advisor can walk through this with you. Approvals happen at the counter or on the estimate we send you.</p>
    </aside>
</div>
