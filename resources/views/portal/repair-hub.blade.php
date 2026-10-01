<x-portal.app>
    @php
        $shopPhone = $shop['phone_display'] ?? $shop['phone'] ?? null;
        $concerns = $concerns ?? collect();
    @endphp

    <section class="customer-panel customer-panel--flush overflow-hidden">
        <div class="border-b border-slate-200/80 px-4 py-6 sm:px-6 lg:px-8">
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#0099cc]">Your vehicle online</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $vehicle_line }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                RO #{{ $repair_order->repair_order_id }}
                @if (filled($plate))
                    · {{ $plate }}
                @endif
            </p>
            @if (filled($shopPhone))
                <p class="mt-3 text-sm text-slate-600">Questions? Call {{ $shopPhone }}</p>
            @endif
        </div>

        <div class="space-y-4 px-4 py-5 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Current status</p>
                <p class="mt-1 text-lg font-semibold text-slate-950">{{ $status_label }}</p>
                @if (filled($status_detail) && $status_detail !== $status_label)
                    <p class="mt-0.5 text-sm text-slate-600">{{ $status_detail }}</p>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Estimate</p>
                        @if (filled($estimate_updated_label))
                            <p class="mt-0.5 text-xs text-slate-500">{{ $estimate_updated_label }}</p>
                        @endif
                    </div>
                    @if (filled($estimate_total))
                        <p class="text-lg font-black tabular-nums text-slate-950">{{ $estimate_total }}</p>
                    @endif
                </div>

                @if ($concerns->isNotEmpty())
                    <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100">
                        @foreach ($concerns as $concern)
                            <li class="flex items-start justify-between gap-3 py-2.5 text-sm">
                                <span class="font-medium text-slate-900">{{ $concern['summary'] ?? 'Work' }}</span>
                                <span class="shrink-0 tabular-nums text-slate-700">{{ $concern['subtotal'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-slate-600">Your advisor is preparing the estimate.</p>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Photos &amp; video</p>
                @if ($shared_evidence->isNotEmpty())
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $photo_count }} {{ $photo_count === 1 ? 'photo' : 'photos' }}
                        @if ($video_count > 0)
                            · {{ $video_count }} {{ $video_count === 1 ? 'video' : 'videos' }}
                        @endif
                    </p>
                    @include('operations.evidence.partials.evidence-items', ['items' => $shared_evidence])
                @else
                    <p class="mt-2 text-sm text-slate-600">Photos and video appear here when your shop shares them.</p>
                @endif
            </section>

            @php
                $inspection = $inspection ?? ['ready' => false, 'finding_count' => 0, 'url' => null];
            @endphp
            <section @class([
                'rounded-xl border px-4 py-4',
                'border-slate-200 bg-white shadow-sm' => (bool) ($inspection['ready'] ?? false),
                'border-dashed border-slate-200 bg-slate-50/80' => ! ($inspection['ready'] ?? false),
            ])>
                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Inspection</p>
                @if ($inspection['ready'] ?? false)
                    <p class="mt-2 text-sm text-slate-600">
                        {{ (int) ($inspection['finding_count'] ?? 0) }}
                        {{ str('finding')->plural((int) ($inspection['finding_count'] ?? 0)) }}
                        recorded for this visit.
                    </p>
                    <a
                        href="{{ $inspection['url'] }}"
                        class="mt-3 inline-flex min-h-9 items-center rounded-md bg-[#0099cc] px-3 text-xs font-semibold text-white no-underline hover:bg-[#0086b3]"
                    >View inspection report</a>
                @else
                    <p class="mt-2 text-sm text-slate-600">Inspection results will appear here when available.</p>
                @endif
            </section>
        </div>
    </section>
</x-portal.app>
