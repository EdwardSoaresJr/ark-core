<x-operations.app title="Work Order - {{ $landing['ro_label'] }}">
    @php
        /** @var array<string, mixed> $landing */
        $coverage = $inspectionCoverage ?? $landing['coverage'];
        $canRecord = (bool) ($coverage['can_record'] ?? false);
        $showInspection = $canRecord && ! $repairOrder->isTerminal();
        $captureUrl = $landing['capture_url'] ?? $coverage['capture_url'] ?? $landing['walk_url'];
        $walkUrl = $landing['walk_url'] ?? $coverage['walk_url'];
        $tabletUrl = $landing['tablet_url'] ?? $coverage['tablet_url'];
        $captureSurface = $landing['capture_surface'] ?? $coverage['capture_surface'] ?? 'desktop_walk';
    @endphp

    <section class="mx-auto max-w-xl px-3 py-3 sm:px-4" data-technician-production-landing>
        <header class="mb-3">
            <a href="{{ $landing['back_url'] }}" class="ops-page-link text-sm">← Workboard</a>
            <p class="mt-2 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Work order</p>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-950 sm:text-2xl">{{ $landing['vehicle_label'] }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                <span class="font-semibold text-slate-800">{{ $landing['ro_label'] }}</span>
                <span class="text-slate-400">·</span>
                {{ $landing['customer_label'] }}
                <span class="text-slate-400">·</span>
                {{ $landing['status_label'] }}
            </p>
        </header>

        <section class="mb-3 border border-slate-200 bg-white px-3 py-3 sm:px-4" data-tech-why-here>
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Why it’s here</p>
            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $landing['why_here'] }}</p>
        </section>

        @if (count($landing['concerns']) > 0)
            <section class="mb-3 border border-slate-200 bg-white px-3 py-3 sm:px-4" data-tech-concerns>
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Owned Repair Actions</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($landing['concerns'] as $concern)
                        <li class="border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3 text-sm">
                                <span class="font-semibold text-slate-950">{{ $concern['summary'] }}</span>
                                <span class="shrink-0 text-xs font-medium text-slate-500">
                                    {{ $concern['status_label'] ?? $concern['disposition_label'] }}
                                </span>
                            </div>
                            @if (! empty($concern['latest_update']))
                                <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Update</p>
                                <p class="mt-0.5 whitespace-pre-wrap text-sm text-slate-800">{{ $concern['latest_update'] }}</p>
                                @if (! empty($concern['updated_at']))
                                    <p class="mt-0.5 text-[11px] text-slate-400">Updated {{ $concern['updated_at'] }}</p>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($showInspection)
            <section class="mb-3 border-2 border-slate-900 bg-white px-3 py-3 sm:px-4" data-inspection-entry>
                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Inspection</p>
                @if (! empty($coverage['template_name']))
                    <p class="mt-1 text-xs font-semibold text-slate-600" data-inspection-template-name>{{ $coverage['template_name'] }}</p>
                @endif
                <div class="mt-1" data-inspection-coverage data-inspection-posture="{{ $coverage['posture_key'] ?? '' }}">
                    <p class="text-lg font-black text-slate-950">{{ $coverage['posture_headline'] ?? $coverage['posture_label'] }}</p>
                    @if (filled($coverage['posture_detail'] ?? null))
                        <p class="text-sm font-semibold text-slate-600">{{ $coverage['posture_detail'] }}</p>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600">{{ $landing['next_action'] }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <a
                        href="{{ $captureUrl }}"
                        class="inline-flex rounded-sm bg-slate-950 px-3 py-2.5 text-sm font-bold text-white hover:bg-slate-800"
                        data-inspection-cta
                        data-inspection-capture-cta
                        data-capture-surface="{{ $captureSurface }}"
                        data-desktop-walk-url="{{ $walkUrl }}"
                        data-tablet-url="{{ $tabletUrl }}"
                    >{{ $coverage['cta_label'] }}</a>
                    <details class="text-xs text-slate-500">
                        <summary class="cursor-pointer font-semibold text-slate-600 hover:text-slate-900">Other layout</summary>
                        <div class="mt-2 flex flex-col gap-1.5">
                            <a
                                href="{{ $tabletUrl }}"
                                class="font-semibold text-slate-700 underline-offset-2 hover:underline"
                                data-inspection-tablet
                            >Bay layout</a>
                            <a
                                href="{{ $walkUrl }}"
                                class="font-semibold text-slate-700 underline-offset-2 hover:underline"
                                data-inspection-desk
                            >Desk layout</a>
                        </div>
                    </details>
                </div>
            </section>
        @else
            <section class="mb-3 border border-slate-200 bg-slate-50 px-3 py-3 sm:px-4">
                <p class="text-sm font-semibold text-slate-800">Inspection recording isn’t available on this repair order for your account.</p>
            </section>
        @endif
    </section>
</x-operations.app>
