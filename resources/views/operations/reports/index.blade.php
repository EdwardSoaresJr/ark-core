<x-operations.app title="Reports">
    <section class="ops-reports-index space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Reports</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Pick a report</h1>
                <p class="mt-1 max-w-2xl text-xs text-slate-500">
                    Same operational truth as before — one entry point instead of landing inside Executive Pulse.
                    <strong class="font-semibold text-slate-600">End of Day</strong> first; drill into tabs when you need depth.
                </p>
            </div>
        </div>

        @foreach ($sections as $section)
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $section['label'] }}</p>
                    <p class="text-xs text-slate-400">{{ $section['description'] }}</p>
                </div>
                <div class="ops-reports-catalog">
                    @foreach ($section['cards'] as $card)
                        <a href="{{ $card['url'] }}" class="ops-reports-catalog-card">
                            <span class="ops-reports-catalog-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="none">
                                    <path d="M4 15.5h12M5.5 13V8M10 13V4.5M14.5 13v-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </span>
                            <span class="ops-reports-catalog-card__title">{{ $card['title'] }}</span>
                            <span class="ops-reports-catalog-card__hint">{{ $card['hint'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
</x-operations.app>
