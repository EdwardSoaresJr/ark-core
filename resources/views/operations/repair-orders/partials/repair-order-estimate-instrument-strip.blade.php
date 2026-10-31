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
@endphp

@if (count($estimateInstruments['instruments'] ?? []) > 0)
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-2" aria-label="Estimate instruments" data-estimate-instruments>
        @foreach ($estimateInstruments['instruments'] as $instrument)
            @php
                $instrumentColor = $instrumentColors[$instrument['tone'] ?? ''] ?? 'secondary';
            @endphp
            <div class="col">
                <x-operations.inspect-popover
                    :title="$instrument['inspect']['title'] ?? null"
                    :items="$instrument['inspect']['items'] ?? []"
                    :footer="$instrument['inspect']['footer'] ?? null"
                    align="start"
                    class="card card-sm h-100"
                    data-instrument="{{ $instrument['key'] }}"
                >
                    <span class="card-body d-block">
                        <span class="subheader d-block mb-1">{{ $instrument['label'] }}</span>
                        <span class="d-flex flex-wrap align-items-center gap-2">
                            <span class="h3 mb-0">{{ $instrument['value'] }}</span>
                            @if (filled($instrument['badge'] ?? null))
                                <span class="badge bg-{{ $instrumentColor }}-lt">{{ $instrument['badge'] }}</span>
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
                    </span>
                </x-operations.inspect-popover>
            </div>
        @endforeach
    </div>
@endif
