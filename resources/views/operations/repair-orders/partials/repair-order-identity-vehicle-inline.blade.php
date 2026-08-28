@php
    $vehicle = $repairOrder->vehicle;
    $serviceLaneLayout = $serviceLaneLayout ?? false;
    $hideMileageLines = $hideMileageLines ?? true;
    $mileageLineLabels = ['Mileage'];
@endphp

<div class="ops-identity-present" data-identity-present="vehicle">
    <button
        type="button"
        @class([
            'ops-identity-title-link text-left',
            'mt-0.5' => ! $serviceLaneLayout,
            'ops-service-lane-vehicle-name' => $serviceLaneLayout,
        ])
        title="Open to edit vehicle"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'vehicle-identity', invokeEl: $event.currentTarget } }))"
    >
        {{ $identity['vehicle']['title'] }}
    </button>

    @include('operations.repair-orders.partials.repair-order-vehicle-change', [
        'repairOrder' => $repairOrder,
    ])

    @if ($serviceLaneLayout)
        @php
            $scanParts = [];
            if (filled($scanMileage ?? null)) {
                $scanParts[] = ['key' => 'mileage', 'value' => $scanMileage, 'emphasis' => true];
            }
            if (filled($scanPlate ?? null)) {
                $scanParts[] = ['key' => 'plate', 'value' => $scanPlate];
            }
        @endphp
        @if (count($scanParts) > 0)
            <p class="ops-service-lane-recognition-meta">
                @foreach ($scanParts as $index => $part)
                    @if ($index > 0)<span class="ops-service-lane-sep">·</span>@endif
                    <span @class(['ops-service-lane-mileage-scan' => ! empty($part['emphasis'])])>{{ $part['value'] }}</span>
                @endforeach
            </p>
        @endif
    @elseif (filled($identity['vehicle']['subtitle'] ?? null))
        <p class="ops-meta mt-0.5">{{ $identity['vehicle']['subtitle'] }}</p>
    @endif

    @if (! filled($vehicle->authoritativeVin()))
        <div class="mt-1 flex flex-wrap items-center gap-1.5">
            @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['repairOrder' => $repairOrder])
        </div>
    @endif

    @unless ($serviceLaneLayout)
        <dl class="mt-1.5 space-y-0.5">
            @foreach ($identity['vehicle']['lines'] as $line)
                @if ($hideMileageLines && in_array($line['label'], $mileageLineLabels, true))
                    @continue
                @endif
                <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
                    <dt class="font-semibold text-slate-500">{{ $line['label'] }}</dt>
                    <dd class="min-w-0 break-words font-semibold text-slate-800">
                        @if ($line['label'] === 'VIN')
                            <x-operations.vin-display :vin="$line['value']" />
                        @else
                            {{ $line['value'] }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endunless
</div>
