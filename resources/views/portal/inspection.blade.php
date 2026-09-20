<x-portal.app>
    @php
        $shop = $snapshot['shop'] ?? [];
        $vehicle = $snapshot['vehicle'] ?? [];
        $findings = collect($snapshot['findings'] ?? [])->filter(fn ($finding): bool => is_array($finding));
        $shopPhone = $shop['phone_display'] ?? $shop['phone'] ?? null;
        $shopPhoneTel = preg_replace('/\D+/', '', (string) ($shop['phone'] ?? $shopPhone ?? ''));
    @endphp

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
        <div class="border-b border-slate-200/80 bg-gradient-to-b from-slate-50 to-white px-4 py-6 sm:px-6">
            @if ($staffPreview ?? false)
                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Staff preview</p>
                    <p class="mt-1 text-amber-900">This view is not logged as a customer opening the inspection.</p>
                </div>
            @endif

            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#0099cc]">Vehicle inspection</p>
            <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-slate-950 sm:text-[1.75rem]">
                {{ $vehicle['display_name'] ?? 'Your vehicle' }}
            </h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                What we found on your vehicle. Each item shows the finding first, then any measurement or notes.
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                    Repair order #{{ $snapshot['repair_order_id'] ?? $repairOrder->repair_order_id }}
                </span>
                @if (filled($shop['name'] ?? null))
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ $shop['name'] }}
                    </span>
                @endif
            </div>

        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($findings as $finding)
                @php
                    $tone = $finding['intent_tone'] ?? 'neutral';
                    $toneClass = match ($tone) {
                        'danger', 'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
                        'warning', 'amber' => 'border-amber-200 bg-amber-50 text-amber-950',
                        'success', 'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
                        'sky' => 'border-sky-200 bg-sky-50 text-sky-900',
                        default => 'border-slate-200 bg-slate-50 text-slate-800',
                    };
                    $intentLabel = match (strtolower((string) ($finding['intent'] ?? ''))) {
                        'safety' => 'Needs attention',
                        'maintenance' => 'Maintenance',
                        'diagnostic' => 'Needs more testing',
                        'verification' => 'Checking a repair',
                        'observation' => 'Note',
                        default => $finding['intent'] ?? null,
                    };
                @endphp
                <article class="px-4 py-5 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                {{ $finding['category'] ?? 'Inspection' }}
                            </p>
                            <h2 class="mt-1 text-lg font-bold text-slate-950">{{ $finding['label'] ?? 'Finding' }}</h2>
                        </div>
                        @if (filled($intentLabel))
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $toneClass }}">
                                {{ $intentLabel }}
                            </span>
                        @endif
                    </div>

                    @if (filled($finding['measurement'] ?? null))
                        <p class="mt-3 text-sm font-semibold text-slate-800">
                            What we measured: {{ $finding['measurement'] }}
                        </p>
                    @endif

                    @if (filled($finding['note'] ?? null))
                        <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $finding['note'] }}</p>
                    @endif

                    @php($photos = collect($finding['photos'] ?? []))
                    @if ($photos->isNotEmpty())
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($photos as $photo)
                                @if (($photo['is_image'] ?? false) && filled($photo['url'] ?? null))
                                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                        <img src="{{ $photo['url'] }}" alt="" class="aspect-[4/3] h-full w-full object-cover">
                                    </a>
                                @elseif (($photo['is_video'] ?? false) && filled($photo['url'] ?? null))
                                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-950">
                                        <video src="{{ $photo['url'] }}" controls playsinline class="aspect-[4/3] w-full object-cover"></video>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <div class="px-4 py-10 text-center sm:px-6">
                    <p class="text-sm text-slate-600">No inspection findings are on this link yet.</p>
                </div>
            @endforelse
        </div>

        <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-5 sm:px-6">
            @include('portal.partials._shop-contact-card', [
                'shopPhone' => $shopPhone,
                'shopPhoneTel' => $shopPhoneTel,
            ])
        </div>
    </section>
</x-portal.app>
