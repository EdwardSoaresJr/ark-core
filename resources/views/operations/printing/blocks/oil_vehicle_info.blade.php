@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $ctx */
    $merge = $mergeVehicleMileage ?? false;
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '13px',
        'lg' => '12px',
        'sm' => '11px',
        default => '12px',
    };
    $line = ($merge && $ctx->vehicleLineWithCurrentMileage !== null && $ctx->vehicleLineWithCurrentMileage !== '')
        ? $ctx->vehicleLineWithCurrentMileage
        : $ctx->vehicleLine;
@endphp
@if(trim($line) !== '')
<div class="kt-row kt-oil-veh" style="font-size:{{ $fs }};font-weight:500;color:#111;line-height:1.15;">{{ $line }}</div>
@endif
