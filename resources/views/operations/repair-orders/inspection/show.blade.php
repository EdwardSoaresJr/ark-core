@php
    $isTablet = (bool) ($is_tablet_surface ?? false);
    $canEdit = $canRecordFindings ?? false;
    $vehicleTitle = $identity['vehicle']['title'] ?? ($repairOrder->vehicle?->display_name ?? 'Vehicle');
    $checked = (int) ($progress['checked'] ?? $coverage['checked'] ?? 0);
    $total = (int) ($progress['total'] ?? $coverage['total'] ?? 0);
    $remaining = (int) ($progress['remaining'] ?? max(0, $total - $checked));
    $postureLabel = $checked > 0
        ? ($total > 0
            ? ($remaining > 0
                ? "{$checked} of {$total} checked · {$remaining} remaining"
                : "{$checked} of {$total} checked")
            : "{$checked} checked")
        : ($total > 0 ? "0 of {$total} checked · {$remaining} remaining" : 'Not Started');
    $walkMode = $walk_mode ?? 'empty';
    $sectionsUrl = $sections_url ?? route('operations.repair-orders.inspection.show', $repairOrder);
@endphp

@if ($isTablet)
    <x-layouts.inspection-tablet :title="'Inspection · RO #'.$repairOrder->repair_order_id">
        <div class="ops-inspection-tablet-stage" data-inspection-surface="tablet">
            <div class="ops-inspection-tablet-card ops-inspection-tablet-card--sections">
                <header class="ops-inspection-tablet__top">
                    <p class="ops-inspection-tablet__eyebrow">Vehicle inspection</p>
                    <h1 class="ops-inspection-tablet__vehicle">{{ $vehicleTitle }}</h1>
                    <p class="ops-inspection-tablet__meta">
                        RO #{{ $repairOrder->repair_order_id }}
                        · <span data-inspection-coverage>{{ $postureLabel }}</span>
                    </p>
                </header>

                @unless ($canEdit)
                    <p class="ops-inspection-tablet__warn">
                        Recording is limited to the assigned technician or an advisor.
                    </p>
                @endunless

                @if ($walk_enabled && $walkMode === 'sections' && $section_walk)
                    @include('operations.repair-orders.inspection.partials.section-workspace', [
                        'repairOrder' => $repairOrder,
                        'section_walk' => $section_walk,
                        'photo_purposes' => $photo_purposes ?? [],
                        'canEdit' => $canEdit,
                        'tabletMode' => true,
                        'template_name' => $template_name ?? null,
                    ])
                @elseif ($walk_enabled && $walkMode === 'point' && $living_record)
                    <p class="ops-inspection-sections__back">
                        <a href="{{ $sectionsUrl }}">← Back to sections</a>
                    </p>
                    @include('operations.repair-orders.inspection.partials.walk-workspace', [
                        'repairOrder' => $repairOrder,
                        'living_record' => $living_record,
                        'walk_points' => $walk_points,
                        'condition_options' => $condition_options,
                        'progress' => $progress,
                        'photo_purposes' => $photo_purposes,
                        'canEdit' => $canEdit,
                        'tabletMode' => true,
                        'template_name' => $template_name ?? null,
                        'sections_url' => $sectionsUrl,
                    ])
                @else
                    <div class="ops-inspection-walk-empty">
                        <p class="ops-inspection-walk-empty__copy">No inspection points are configured for this repair order yet.</p>
                    </div>
                @endif

                <details class="ops-inspection-tablet__other">
                    <summary>Other Findings</summary>
                    <p class="ops-inspection-tablet__other-hint">See something that wasn't covered above? Add it here.</p>
                    <div class="ops-inspection-tablet__other-body">
                        @include('operations.repair-orders.inspection.partials.finding-capture')
                    </div>
                </details>
            </div>
        </div>
    </x-layouts.inspection-tablet>
@else
    <x-operations.app title="Vehicle Inspection - RO #{{ $repairOrder->repair_order_id }}">
        <section class="ops-inspection-production ops-inspection-production--walk mx-auto max-w-3xl px-3 py-3 sm:px-4" data-inspection-surface="standard">
            <header class="mb-3">
                <h1 class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl">{{ $vehicleTitle }}</h1>
                <p class="mt-1 text-sm font-semibold text-slate-600">
                    RO #{{ $repairOrder->repair_order_id }}
                    · <span data-inspection-coverage>{{ $postureLabel }}</span>
                </p>
            </header>

            @unless ($canEdit)
                <p class="mb-3 border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                    You can review this inspection, but recording is limited to the assigned technician or an advisor.
                </p>
            @endunless

            @if ($walk_enabled && $walkMode === 'sections' && $section_walk)
                @include('operations.repair-orders.inspection.partials.section-workspace', [
                    'repairOrder' => $repairOrder,
                    'section_walk' => $section_walk,
                    'photo_purposes' => $photo_purposes ?? [],
                    'canEdit' => $canEdit,
                    'tabletMode' => false,
                    'template_name' => $template_name ?? null,
                ])
            @elseif ($walk_enabled && $walkMode === 'point' && $living_record)
                <p class="ops-inspection-sections__back mb-3">
                    <a href="{{ $sectionsUrl }}" class="text-sm font-semibold text-[#0099cc] no-underline hover:underline">← Back to sections</a>
                </p>
                @include('operations.repair-orders.inspection.partials.walk-workspace', [
                    'repairOrder' => $repairOrder,
                    'living_record' => $living_record,
                    'walk_points' => $walk_points,
                    'condition_options' => $condition_options,
                    'progress' => $progress,
                    'photo_purposes' => $photo_purposes,
                    'canEdit' => $canEdit,
                    'tabletMode' => false,
                    'template_name' => $template_name ?? null,
                    'sections_url' => $sectionsUrl,
                ])
            @else
                <div class="ops-inspection-walk-empty">
                    <p class="ops-inspection-walk-empty__copy">No inspection points are configured for this repair order yet.</p>
                </div>
            @endif

            <section class="ops-inspection-other-findings mt-6 border-t border-slate-200 pt-4">
                <h2 class="text-sm font-bold text-slate-950">Other Findings</h2>
                <p class="mt-1 text-xs text-slate-600">See something that wasn't covered above? Add it here.</p>
                <div class="mt-3">
                    @include('operations.repair-orders.inspection.partials.finding-capture')
                </div>
            </section>
        </section>
    </x-operations.app>
@endif
