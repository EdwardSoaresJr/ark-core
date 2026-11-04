<x-portal.app>
    @php
        $shopPhone = $shop['phone_display'] ?? $shop['phone'] ?? null;
        $concerns = $concerns ?? collect();
    @endphp

    <section class="customer-panel customer-panel--flush overflow-hidden">
        <div class="border-b border-slate-200/80 px-4 py-6 sm:px-6 lg:px-8">
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[color:var(--public-link)]">Your vehicle online</p>
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
            @php
                $stage = match ((string) $repair_order->status) {
                    'draft', 'estimate' => 0,
                    'waiting_approval' => 1,
                    'approved', 'ready_for_work', 'in_progress', 'waiting_parts', 'quality_check' => 2,
                    'completed', 'invoiced', 'ready_pickup' => 3,
                    'closed' => 4,
                    default => null,
                };
                $stages = ['Checked in', 'Your approval', 'Repair in progress', 'Ready for pickup'];
            @endphp
            <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm" data-repair-progress>
                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Current status</p>
                <p class="mt-1 text-lg font-semibold text-slate-950">{{ $status_label }}</p>
                @if (filled($status_detail) && $status_detail !== $status_label)
                    <p class="mt-0.5 text-sm text-slate-600">{{ $status_detail }}</p>
                @endif
                @if ($stage !== null)
                    <ol class="mt-4 grid grid-cols-4 gap-1.5" aria-label="Repair progress">
                        @foreach ($stages as $index => $label)
                            <li @if ($index === min($stage, 3) && $stage < 4) aria-current="step" @endif>
                                <span @class([
                                    'block h-1.5 rounded-full',
                                    'bg-[color:var(--public-brand)]' => $index <= $stage,
                                    'bg-slate-200' => $index > $stage,
                                ])></span>
                                <span @class([
                                    'mt-1.5 block text-[11px] font-semibold leading-tight sm:text-xs',
                                    'text-slate-950' => $index <= $stage,
                                    'text-slate-500' => $index > $stage,
                                ])>{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
                @if ((string) $repair_order->status === 'waiting_approval')
                    <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2.5 text-sm leading-6 text-amber-900">
                        Your estimate is ready. Open the estimate link we sent you by text or email to approve, defer, or decline each repair.
                    </p>
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
                        class="mt-3 inline-flex min-h-9 items-center rounded-[var(--public-radius-control)] bg-[color:var(--public-brand)] px-3 text-xs font-semibold text-[color:var(--public-on-brand)] no-underline hover:bg-[color:var(--public-brand-hover)]"
                    >View inspection report</a>
                @else
                    <p class="mt-2 text-sm text-slate-600">Inspection results will appear here when available.</p>
                @endif
            </section>
        </div>
    </section>
</x-portal.app>
