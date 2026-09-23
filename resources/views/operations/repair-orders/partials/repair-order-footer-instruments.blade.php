@php
    use App\Ark\Operations\RepairOrders\EstimateTotals;
    use App\Ark\Operations\RepairOrders\RepairOrderEstimateInstrumentProjection;

    $instruments = $totals instanceof EstimateTotals
        ? (RepairOrderEstimateInstrumentProjection::for($repairOrder, $totals)['instruments'] ?? [])
        : [];
@endphp

@if (count($instruments) > 0)
    <div class="ops-ro-footer__instruments" aria-label="Estimate instruments">
        @foreach ($instruments as $instrument)
            <x-operations.inspect-popover
                :title="$instrument['inspect']['title'] ?? null"
                :items="$instrument['inspect']['items'] ?? []"
                :footer="$instrument['inspect']['footer'] ?? null"
                align="end"
                above
                class="ops-ro-footer__instrument"
            >
                <span class="ops-ro-footer__instrument-trigger">
                    <span class="ops-ro-footer__instrument-label">{{ $instrument['label'] }}</span>
                    <span class="ops-ro-footer__instrument-value">{{ $instrument['value'] }}</span>
                    @if (filled($instrument['badge'] ?? null))
                        <span @class([
                            'ops-ro-footer__instrument-badge',
                            'ops-ro-footer__instrument-badge--'.$instrument['tone'] => filled($instrument['tone'] ?? null),
                        ])>{{ $instrument['badge'] }}</span>
                    @endif
                </span>
            </x-operations.inspect-popover>
        @endforeach
    </div>
@endif
