@php
    use App\Ark\Operations\RepairOrders\RepairOrderEstimateInstrumentProjection;

    $estimateInstruments = $estimateInstruments
        ?? RepairOrderEstimateInstrumentProjection::for($repairOrder, $totals);
    $instrumentColors = [
        'excellent' => 'green',
        'good' => 'green',
        'healthy' => 'teal',
        'thin' => 'yellow',
        'warn' => 'orange',
        'low' => 'red',
    ];
    $partsBlockingCount = (int) ($partsBlockingCount ?? 0);
@endphp

{{-- authorized-money is refreshed by both line and authorization saves, so the instruments match the rail after either. --}}
<div id="estimate-instruments" data-continuity-region="authorized-money">
@if (count($estimateInstruments['instruments'] ?? []) > 0)
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-2" aria-label="Estimate instruments" data-estimate-instruments>
        @foreach ($estimateInstruments['instruments'] as $instrument)
            @php
                $instrumentColor = $instrumentColors[$instrument['tone'] ?? ''] ?? 'secondary';
                $instrumentKey = (string) ($instrument['key'] ?? '');
                $opensContext = match ($instrumentKey) {
                    'approval' => 'auth',
                    'parts' => 'parts',
                    'total' => 'portal',
                    default => null,
                };
                $partsBadge = $instrumentKey === 'parts' && $partsBlockingCount > 0
                    ? $partsBlockingCount.' blocking'
                    : null;
            @endphp
            <div class="col">
                <div
                    @class(['h-100', 'ops-estimate-instrument-opens-context' => $opensContext !== null])
                    @if ($opensContext !== null)
                        data-estimate-context-open="{{ $opensContext }}"
                        role="button"
                        tabindex="0"
                        @click="toggleEstimateContext('{{ $opensContext }}')"
                        @keydown.enter.prevent="toggleEstimateContext('{{ $opensContext }}')"
                        @keydown.space.prevent="toggleEstimateContext('{{ $opensContext }}')"
                    @endif
                >
                    <x-operations.inspect-popover
                        :title="$instrument['inspect']['title'] ?? null"
                        :items="$instrument['inspect']['items'] ?? []"
                        :footer="$instrument['inspect']['footer'] ?? null"
                        align="start"
                        block
                        class="card card-sm h-100"
                        data-instrument="{{ $instrumentKey }}"
                    >
                        <span class="card-body d-block">
                            <span class="subheader d-block mb-1">{{ $instrument['label'] }}</span>
                            <span class="d-flex flex-wrap align-items-center gap-2">
                                <span class="h3 mb-0">{{ $instrument['value'] }}</span>
                                @if (filled($instrument['badge'] ?? null))
                                    <span class="badge bg-{{ $instrumentColor }}-lt">{{ $instrument['badge'] }}</span>
                                @endif
                                @if ($partsBadge)
                                    <span class="badge bg-orange-lt" data-parts-blocking-badge>{{ $partsBadge }}</span>
                                @endif
                            </span>
                            @if (($instrument['meter_percent'] ?? null) !== null)
                                <span class="progress progress-sm mt-2" aria-hidden="true">
                                    <span
                                        class="progress-bar bg-{{ $instrumentColor }}"
                                        style="width: {{ max(0, min(100, (int) $instrument['meter_percent'])) }}%"
                                    ></span>
                                </span>
                            @endif
                            @if ($opensContext === 'auth')
                                <span class="d-block small text-secondary mt-1">Authorization</span>
                            @elseif ($opensContext === 'portal')
                                <span class="d-block small text-secondary mt-1">Customer View</span>
                            @elseif ($opensContext === 'parts')
                                <span class="d-block small text-secondary mt-1">Parts readiness</span>
                            @endif
                        </span>
                    </x-operations.inspect-popover>
                </div>
            </div>
        @endforeach
    </div>
@endif
</div>
