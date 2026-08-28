@php
    use App\Ark\Operations\Inspections\InspectionCoverageProjection;

    $coverage = $inspectionCoverage ?? InspectionCoverageProjection::for($repairOrder, auth()->user());
    $showEntry = ($coverage['can_record'] ?? false) && ! ($repairOrder->isTerminal() ?? false);
    /** @var string $entryDestination workspace = Inspection tab; capture = inspection host (Builder) */
    $entryDestination = $entryDestination ?? 'capture';
    $opensWorkspace = $entryDestination === 'workspace';
@endphp

@if ($showEntry)
    <section
        @class([
            'ops-inspection-entry',
            'ops-inspection-entry--builder' => ! $opensWorkspace,
        ])
        data-inspection-entry
        @if (! $opensWorkspace) data-inspection-entry-builder @endif
    >
        <div class="ops-inspection-entry__row">
            <div class="ops-inspection-entry__identity">
                <span class="ops-inspection-entry__label">Inspection</span>
                @if (! empty($coverage['template_name']))
                    <span class="ops-inspection-entry__template" data-inspection-template-name>{{ $coverage['template_name'] }}</span>
                @endif
                <div class="ops-inspection-entry__choice">
                    @include('operations.repair-orders.inspection.partials.builder-template-select', ['repairOrder' => $repairOrder])
                </div>
            </div>

            <div class="ops-inspection-entry__action">
                <div class="ops-inspection-entry__posture" data-inspection-coverage data-inspection-posture="{{ $coverage['posture_key'] }}">
                    <span class="ops-inspection-entry__status">{{ $coverage['posture_headline'] }}</span>
                    @if (filled($coverage['posture_detail']))
                        <span class="ops-inspection-entry__detail">{{ $coverage['posture_detail'] }}</span>
                    @endif
                </div>
                @if ($opensWorkspace)
                    <a
                        href="#inspect"
                        class="ops-inspection-entry__cta"
                        data-inspection-cta
                        data-inspection-cta-workspace
                        onclick="event.preventDefault(); window.arkSelectRepairOrderWorkspaceTab?.('inspect');"
                    >Inspection</a>
                @else
                    <a
                        href="{{ $coverage['capture_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ops-inspection-entry__cta"
                        data-inspection-cta
                        data-inspection-cta-capture
                        data-inspection-capture-cta
                        data-capture-surface="{{ $coverage['capture_surface'] }}"
                        data-desktop-walk-url="{{ $coverage['walk_url'] }}"
                        data-tablet-url="{{ $coverage['tablet_url'] }}"
                    >Open Inspection</a>
                @endif
            </div>
        </div>
    </section>
@endif
