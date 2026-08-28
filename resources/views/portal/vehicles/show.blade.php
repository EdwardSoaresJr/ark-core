@php
    $vehicleInfo = $detail['vehicle'] ?? [];
    $activeVisit = $detail['active_visit'] ?? null;
    $lastVisit = $detail['last_visit'] ?? null;
    $recentVisits = collect($detail['recent_visits'] ?? []);
    $documents = $detail['documents'] ?? ['total_count' => 0, 'items' => []];
    $shop = $detail['shop'] ?? [];
@endphp

<x-portal.app>
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <div class="space-y-6">
                <div class="public-hero">
                    <a
                        href="{{ route('portal.home') }}"
                        class="text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
                    >
                        ← All vehicles
                    </a>
                    <h1 class="public-hero__title mt-2">
                        {{ $vehicleInfo['display_name'] ?? $vehicle->display_name }}
                    </h1>
                    @if (filled($vehicleInfo['plate'] ?? null) || filled($vehicleInfo['vin'] ?? null))
                        <p class="mt-2 text-sm text-slate-600">
                            @if (filled($vehicleInfo['plate'] ?? null))
                                Plate {{ $vehicleInfo['plate'] }}
                            @endif
                            @if (filled($vehicleInfo['plate'] ?? null) && filled($vehicleInfo['vin'] ?? null))
                                <span class="text-slate-300">·</span>
                            @endif
                            @if (filled($vehicleInfo['vin'] ?? null))
                                VIN {{ $vehicleInfo['vin'] }}
                            @endif
                        </p>
                    @endif

                </div>

                @if ($activeVisit)
                    <section class="public-panel overflow-hidden p-0">
                        <div @class([
                            'border-b px-5 py-4 sm:px-6',
                            'border-amber-200 bg-amber-50' => $activeVisit['needs_attention'],
                            'border-emerald-200 bg-emerald-50' => ! $activeVisit['needs_attention'],
                        ])>
                            <p @class([
                                'text-sm font-semibold uppercase tracking-wide',
                                'text-amber-800' => $activeVisit['needs_attention'],
                                'text-emerald-800' => ! $activeVisit['needs_attention'],
                            ])>
                                @if ($activeVisit['needs_attention'])
                                    Needs your attention
                                @else
                                    Active visit
                                @endif
                            </p>
                            <p class="mt-1 text-xl font-bold text-slate-950">{{ $activeVisit['summary'] }}</p>
                        </div>
                        <div class="space-y-4 px-5 py-5 sm:px-6">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ $activeVisit['status_label'] }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Repair order</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">#{{ $activeVisit['repair_order_id'] }}</p>
                                </div>
                            </div>
                            @if (filled($activeVisit['opened_at_label'] ?? null))
                                <p class="text-sm text-slate-600">Opened {{ $activeVisit['opened_at_label'] }}</p>
                            @endif

                            <div class="flex flex-wrap gap-3">
                                @if ($activeVisit['needs_attention'] && filled($activeVisit['review_url'] ?? null))
                                    <a
                                        href="{{ $activeVisit['review_url'] }}"
                                        class="inline-flex min-h-11 items-center justify-center rounded-md bg-[#0099cc] px-4 text-sm font-semibold text-white no-underline shadow-sm hover:bg-[#0088b8]"
                                    >
                                        Review {{ $activeVisit['review_label'] ?? 'estimate' }}
                                    </a>
                                @endif
                                @if (filled($activeVisit['inspection_url'] ?? null))
                                    <a
                                        href="{{ $activeVisit['inspection_url'] }}"
                                        class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-900 no-underline hover:bg-slate-50"
                                    >
                                        View inspection
                                    </a>
                                @endif
                            </div>
                        </div>
                    </section>
                @else
                    <section class="public-panel">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Active visit</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">No active visit for this vehicle right now.</p>
                        @if ($lastVisit)
                            <p class="mt-2 text-sm text-slate-600">
                                Last visit: <span class="font-semibold text-slate-900">{{ $lastVisit['summary'] }}</span>
                                · {{ $lastVisit['occurred_at_label'] }}
                            </p>
                        @endif
                    </section>
                @endif

                <section class="public-panel">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Recent visits</h2>
                    @if ($recentVisits->isEmpty())
                        <p class="mt-3 text-sm leading-6 text-slate-600">No prior visits on file.</p>
                    @else
                        <ol class="mt-4 space-y-4">
                            @foreach ($recentVisits as $visit)
                                <li class="flex gap-3 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950">{{ $visit['summary'] }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $visit['occurred_at_label'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">Repair order #{{ $visit['repair_order_id'] }}</p>
                                        @if (filled($visit['inspection_url'] ?? null))
                                            <a
                                                href="{{ $visit['inspection_url'] }}"
                                                class="mt-2 inline-flex text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
                                            >
                                                View inspection
                                            </a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>
        </x-slot:primary>

        <x-slot:rail>
            <section class="public-panel">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Documents</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ $documents['total_count'] }} available
                    </span>
                </div>

                @if (($documents['items'] ?? []) === [])
                    <p class="mt-3 text-sm leading-6 text-slate-600">No documents on file yet.</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($documents['items'] as $document)
                            <li class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-950">{{ $document['label'] }}</p>
                                @if (filled($document['occurred_at_label'] ?? null))
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $document['occurred_at_label'] }}</p>
                                @endif
                                @if (filled($document['review_url'] ?? null) || ($document['has_pdf'] ?? false))
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @if (filled($document['review_url'] ?? null))
                                            <a
                                                href="{{ $document['review_url'] }}"
                                                class="inline-flex min-h-9 items-center justify-center rounded-md bg-[#0099cc] px-3 text-xs font-semibold text-white no-underline hover:bg-[#0088b8]"
                                            >
                                                {{ $document['review_label'] ?? 'Review estimate' }}
                                            </a>
                                        @endif
                                        @if ($document['has_pdf'] ?? false)
                                            <a
                                                href="{{ $document['view_url'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-900 no-underline hover:bg-slate-100"
                                            >
                                                View PDF
                                            </a>
                                            <a
                                                href="{{ $document['download_url'] }}"
                                                class="inline-flex min-h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-900 no-underline hover:bg-slate-100"
                                            >
                                                Download
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @include('portal.partials._customer-aside', [
                'aside' => [
                    'phone_display' => $shop['phone_display'] ?? '',
                    'phone_tel' => $shop['phone_tel'] ?? '',
                    'sms_href' => $shop['sms_href'] ?? '',
                    'business_hours_label' => null,
                    'help_links' => [
                        [
                            'label' => 'Common car problems',
                            'href' => \App\Ark\Customer\CustomerSurfaceUrls::commonProblems(),
                            'description' => 'Plain guides for common issues while you wait.',
                        ],
                        [
                            'label' => 'Text us photos or video',
                            'href' => $shop['sms_href'] ?? '',
                            'description' => 'A picture of the dash, leak, or noise helps us prepare.',
                        ],
                    ],
                    'shop_photos' => $shop['shop_photos'] ?? [],
                ],
            ])
        </x-slot:rail>
    </x-customer.split-page>
</x-portal.app>
