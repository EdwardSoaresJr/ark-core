@php
    use App\Ark\Operations\RepairOrders\RepairOrderEstimateInstrumentProjection;

    $estimateInstruments = $estimateInstruments
        ?? RepairOrderEstimateInstrumentProjection::for($repairOrder, $totals);
@endphp

@if (count($estimateInstruments['instruments'] ?? []) > 0)
    <div class="ops-estimate-instruments" aria-label="Estimate instruments">
        @foreach ($estimateInstruments['instruments'] as $instrument)
            <x-operations.inspect-popover
                :title="$instrument['inspect']['title'] ?? null"
                :items="$instrument['inspect']['items'] ?? []"
                :footer="$instrument['inspect']['footer'] ?? null"
                align="start"
                class="ops-estimate-instruments__gauge ops-estimate-instruments__gauge--{{ $instrument['key'] }}"
            >
                <span class="ops-estimate-instruments__trigger">
                    <span class="ops-estimate-instruments__label">{{ $instrument['label'] }}</span>
                    <span class="ops-estimate-instruments__value-row">
                        <span class="ops-estimate-instruments__value">{{ $instrument['value'] }}</span>
                        @if (filled($instrument['badge'] ?? null))
                            <span @class([
                                'ops-estimate-instruments__badge',
                                'ops-estimate-instruments__badge--'.$instrument['tone'] => filled($instrument['tone'] ?? null),
                            ])>{{ $instrument['badge'] }}</span>
                        @endif
                    </span>
                    @if (($instrument['meter_percent'] ?? null) !== null)
                        <span class="ops-estimate-instruments__meter" aria-hidden="true">
                            <span
                                class="ops-estimate-instruments__meter-fill ops-estimate-instruments__meter-fill--{{ $instrument['tone'] ?? 'neutral' }}"
                                style="width: {{ max(0, min(100, (int) $instrument['meter_percent'])) }}%"
                            ></span>
                        </span>
                    @endif
                </span>
            </x-operations.inspect-popover>
        @endforeach
    </div>
@endif
