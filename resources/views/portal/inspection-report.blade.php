@php
    $shop = $report['shop'] ?? [];
    $vehicle = $report['vehicle'] ?? [];
    $identity = $report['identity'] ?? [];
    $summary = $report['summary'] ?? [];
    $mode = $mode ?? ($report['mode'] ?? 'simple');
    $isDetailed = $mode === 'detailed';
    $shopPhone = $shop['phone'] ?? null;
    $shopPhoneTel = preg_replace('/\D+/', '', (string) ($shopPhone ?? ''));
    $headlineAttention = (int) ($summary['headline_needs_attention'] ?? 0);
    $failedCount = (int) ($summary['failed_count'] ?? 0);
    $needsAttentionOnly = (int) ($summary['needs_attention_count'] ?? 0);
@endphp

<x-portal.app>
    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
        <div class="border-b border-slate-200/80 bg-gradient-to-b from-slate-50 to-white px-4 py-6 sm:px-6">
            @if ($staffPreview ?? false)
                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Staff preview</p>
                    <p class="mt-1 text-amber-900">This view is not logged as a customer opening the inspection.</p>
                </div>
            @endif

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#0099cc]">
                        {{ $identity['title'] ?? 'Vehicle Inspection' }}
                    </p>
                    <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-slate-950 sm:text-[1.75rem]">
                        {{ $vehicle['display_name'] ?? 'Your vehicle' }}
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        @if ($isDetailed)
                            Full inspection record — every checked point, measurement, and customer-facing photo.
                        @else
                            What we checked on your vehicle, with measurements and photos where they matter most.
                        @endif
                    </p>
                </div>
                @if (filled($shop['logo_url'] ?? null))
                    <img src="{{ $shop['logo_url'] }}" alt="" class="h-12 w-auto object-contain">
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-700">
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                    RO #{{ $identity['repair_order_id'] ?? $repairOrder->repair_order_id }}
                </span>
                @if (filled($identity['template_name'] ?? null))
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">{{ $identity['template_name'] }}</span>
                @endif
                @if (filled($vehicle['mileage_in'] ?? null))
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">{{ number_format((int) $vehicle['mileage_in']) }} mi</span>
                @endif
                @if (filled($identity['inspected_at_label'] ?? null))
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">{{ $identity['inspected_at_label'] }}</span>
                @endif
                @if (filled($identity['technician_name'] ?? null))
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Tech {{ $identity['technician_name'] }}</span>
                @endif
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <a
                    href="{{ $access['simple_url'] }}"
                    @class([
                        'inline-flex min-h-9 items-center rounded-md px-3 text-xs font-semibold no-underline',
                        'bg-[#0099cc] text-white' => ! $isDetailed,
                        'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50' => $isDetailed,
                    ])
                >Simple</a>
                <a
                    href="{{ $access['detailed_url'] }}"
                    @class([
                        'inline-flex min-h-9 items-center rounded-md px-3 text-xs font-semibold no-underline',
                        'bg-[#0099cc] text-white' => $isDetailed,
                        'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50' => ! $isDetailed,
                    ])
                >Detailed</a>
                <a
                    href="{{ $access['print_url'] }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex min-h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 no-underline hover:bg-slate-50"
                >Print</a>
                <a
                    href="{{ $access['pdf_url'] }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex min-h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 no-underline hover:bg-slate-50"
                >PDF</a>
            </div>
        </div>

        @if (! ($report['ready'] ?? false))
            <div class="px-4 py-10 text-center sm:px-6">
                <p class="text-sm text-slate-600">No inspection findings are on this report yet.</p>
            </div>
        @else
            <div class="border-b border-slate-100 px-4 py-5 sm:px-6">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Summary</p>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3">
                        <p class="text-2xl font-bold text-amber-950">{{ $headlineAttention }}</p>
                        <p class="mt-1 text-xs font-semibold text-amber-900">Need Attention</p>
                        @if ($failedCount > 0)
                            <p class="mt-1 text-[11px] text-amber-800">
                                {{ $needsAttentionOnly }} Needs Attention · {{ $failedCount }} Failed
                            </p>
                        @endif
                    </div>
                    <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-3">
                        <p class="text-2xl font-bold text-sky-950">{{ (int) ($summary['monitor_count'] ?? 0) }}</p>
                        <p class="mt-1 text-xs font-semibold text-sky-900">Monitor</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
                        <p class="text-2xl font-bold text-emerald-950">{{ (int) ($summary['ok_count'] ?? 0) }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-900">Checked OK</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                        <p class="text-2xl font-bold text-slate-900">{{ (int) ($summary['na_count'] ?? 0) }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-700">N/A / Not performed</p>
                    </div>
                </div>
            </div>

            @if ($isDetailed)
                @foreach (($report['categories'] ?? []) as $category)
                    <div class="border-b border-slate-100">
                        <div class="bg-slate-50 px-4 py-3 sm:px-6">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ $category['name'] }}</h2>
                        </div>
                        @foreach (($category['points'] ?? []) as $point)
                            @include('portal.partials._inspection-report-finding', [
                                'point' => $point,
                                'variant' => 'portal',
                                'showVideosPlayable' => true,
                            ])
                        @endforeach
                    </div>
                @endforeach
            @else
                @if (($report['attention_findings'] ?? []) !== [])
                    <div class="border-b border-slate-100">
                        <div class="bg-amber-50 px-4 py-3 sm:px-6">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-amber-900">Needs Attention</h2>
                        </div>
                        @foreach ($report['attention_findings'] as $point)
                            @include('portal.partials._inspection-report-finding', [
                                'point' => $point,
                                'variant' => 'portal',
                                'showVideosPlayable' => true,
                            ])
                        @endforeach
                    </div>
                @endif

                @if (($report['monitor_findings'] ?? []) !== [])
                    <div class="border-b border-slate-100">
                        <div class="bg-sky-50 px-4 py-3 sm:px-6">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-sky-900">Monitor</h2>
                        </div>
                        @foreach ($report['monitor_findings'] as $point)
                            @include('portal.partials._inspection-report-finding', [
                                'point' => $point,
                                'variant' => 'portal',
                                'showVideosPlayable' => true,
                            ])
                        @endforeach
                    </div>
                @endif

                @php
                    $ok = $report['ok_condensed'] ?? ['count' => 0, 'by_category' => []];
                @endphp
                @if (($ok['count'] ?? 0) > 0)
                    <div class="border-b border-slate-100 px-4 py-5 sm:px-6">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-emerald-800">
                            Checked &amp; OK ({{ (int) $ok['count'] }})
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            The rest of the vehicle was checked. These points were in good condition.
                        </p>
                        <ul class="mt-3 space-y-2">
                            @foreach (($ok['by_category'] ?? []) as $group)
                                <li class="text-sm text-slate-800">
                                    <span class="font-semibold">{{ $group['category'] }}</span>
                                    <span class="text-slate-500">({{ (int) $group['count'] }})</span>
                                    — {{ implode(', ', $group['labels'] ?? []) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (($report['na_findings'] ?? []) !== [])
                    <div class="border-b border-slate-100 px-4 py-5 sm:px-6">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600">
                            N/A / Not performed ({{ count($report['na_findings']) }})
                        </h2>
                        <ul class="mt-3 space-y-2">
                            @foreach ($report['na_findings'] as $point)
                                <li class="text-sm text-slate-700">
                                    <span class="font-semibold">{{ $point['label'] }}</span>
                                    @if (filled($point['note'] ?? null))
                                        — {{ $point['note'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        @endif

        <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-5 sm:px-6">
            @include('portal.partials._shop-contact-card', [
                'shopPhone' => $shopPhone,
                'shopPhoneTel' => $shopPhoneTel,
                'heading' => 'Questions about this inspection?',
                'body' => filled($shopPhone)
                    ? 'Ask us about anything on this report. Call or text '.$shopPhone.'.'
                    : 'Ask us about anything on this report.',
            ])
        </div>
    </section>
</x-portal.app>
