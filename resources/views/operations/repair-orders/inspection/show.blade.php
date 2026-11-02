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
    $isPointWalk = $walk_enabled && $walkMode === 'point' && $living_record;
    $sectionsUrl = $sections_url ?? route('operations.repair-orders.inspection.show', $repairOrder);
@endphp

@if ($isTablet)
    @php
        $otherFindingsOpen = old('intent') !== null || request()->boolean('capture');
    @endphp
    <x-layouts.inspection-tablet :title="'Inspection · RO #'.$repairOrder->repair_order_id">
        <div class="page-body" data-inspection-surface="tablet">
            <div class="container-narrow d-flex flex-column gap-3">
                <header class="card" data-inspection-tablet-header>
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm" style="min-width: 0">
                                <div class="subheader">Vehicle inspection</div>
                                <h1 class="mb-1 text-break">{{ $vehicleTitle }}</h1>
                                <div class="text-secondary fw-medium">
                                    RO #{{ $repairOrder->repair_order_id }}
                                    · <span data-inspection-coverage>{{ $postureLabel }}</span>
                                </div>
                            </div>
                            @if ($isPointWalk)
                                <div class="col-12 col-sm-auto">
                                    <a href="{{ $sectionsUrl }}" class="btn btn-lg w-100" data-inspection-sections-back>
                                        <x-tabler.icon name="arrow-left" />
                                        Back to sections
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </header>

                @unless ($canEdit)
                    <div class="alert alert-warning mb-0" role="status">
                        Recording is limited to the assigned technician or an advisor.
                    </div>
                @endunless

                @if ($walk_enabled && $walkMode === 'sections' && $section_walk)
                    @include('operations.repair-orders.inspection.partials.section-workspace-tabler', [
                        'repairOrder' => $repairOrder,
                        'section_walk' => $section_walk,
                        'photo_purposes' => $photo_purposes ?? [],
                        'canEdit' => $canEdit,
                        'tabletMode' => true,
                        'template_name' => $template_name ?? null,
                    ])
                @elseif ($isPointWalk)
                    @include('operations.repair-orders.inspection.partials.walk-workspace-tabler', [
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
                    <div class="card" data-inspection-walk-empty>
                        <div class="card-body text-secondary">No inspection points are configured for this repair order yet.</div>
                    </div>
                @endif

                <section class="card" data-inspection-other-findings>
                    <div class="accordion accordion-flush">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="inspection-other-findings-title">
                                <button
                                    type="button"
                                    @class(['accordion-button py-3 fs-3', 'collapsed' => ! $otherFindingsOpen])
                                    data-bs-toggle="collapse"
                                    data-bs-target="#inspection-other-findings"
                                    aria-controls="inspection-other-findings"
                                    aria-expanded="{{ $otherFindingsOpen ? 'true' : 'false' }}"
                                >Other Findings</button>
                            </h2>
                            <div
                                id="inspection-other-findings"
                                @class(['accordion-collapse collapse', 'show' => $otherFindingsOpen])
                                aria-labelledby="inspection-other-findings-title"
                            >
                                <div class="accordion-body">
                                    <p class="text-secondary mb-3">See something that wasn't covered above? Add it here.</p>
                                    @include('operations.repair-orders.inspection.partials.finding-capture-tabler', ['tabletMode' => true])
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </x-layouts.inspection-tablet>
@else
    <x-operations.app title="Vehicle Inspection - RO #{{ $repairOrder->repair_order_id }}" ui="tabler">
        <x-tabler.page layout="focused" pretitle="Vehicle inspection" :title="$vehicleTitle" stack-actions>
            @if ($isPointWalk)
                <x-slot:actions>
                    <x-tabler.page-action :href="$sectionsUrl" icon="arrow-left" label="Back to sections" data-inspection-sections-back />
                </x-slot:actions>
            @endif

            <div class="d-flex flex-column gap-3" data-inspection-surface="standard">
                <div class="text-secondary fw-medium">
                    RO #{{ $repairOrder->repair_order_id }}
                    · <span data-inspection-coverage>{{ $postureLabel }}</span>
                </div>

                @unless ($canEdit)
                    <div class="alert alert-warning mb-0" role="status">
                        You can review this inspection, but recording is limited to the assigned technician or an advisor.
                    </div>
                @endunless

                @if ($walk_enabled && $walkMode === 'sections' && $section_walk)
                    @include('operations.repair-orders.inspection.partials.section-workspace-tabler', [
                        'repairOrder' => $repairOrder,
                        'section_walk' => $section_walk,
                        'photo_purposes' => $photo_purposes ?? [],
                        'canEdit' => $canEdit,
                        'template_name' => $template_name ?? null,
                    ])
                @elseif ($isPointWalk)
                    @include('operations.repair-orders.inspection.partials.walk-workspace-tabler', [
                        'repairOrder' => $repairOrder,
                        'living_record' => $living_record,
                        'walk_points' => $walk_points,
                        'condition_options' => $condition_options,
                        'progress' => $progress,
                        'photo_purposes' => $photo_purposes,
                        'canEdit' => $canEdit,
                        'template_name' => $template_name ?? null,
                        'sections_url' => $sectionsUrl,
                    ])
                @else
                    <div class="card" data-inspection-walk-empty>
                        <div class="card-body text-secondary">No inspection points are configured for this repair order yet.</div>
                    </div>
                @endif

                <section class="card" aria-labelledby="inspection-other-findings-title" data-inspection-other-findings>
                    <div class="card-body p-3">
                        <h2 class="card-title mb-1" id="inspection-other-findings-title">Other Findings</h2>
                        <p class="text-secondary mb-3">See something that wasn't covered above? Add it here.</p>
                        @include('operations.repair-orders.inspection.partials.finding-capture-tabler')
                    </div>
                </section>
            </div>
        </x-tabler.page>
    </x-operations.app>
@endif
