@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $ctx */
    $merge = $mergeVehicleMileage ?? false;
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '18px',
        'lg' => '17px',
        'sm' => '15px',
        default => '17px',
    };
    $line = ($merge && $ctx->vehicleLineWithCurrentMileage !== null && $ctx->vehicleLineWithCurrentMileage !== '')
        ? null
        : $ctx->mileageCurrentLine;
@endphp
@if($line !== null && $line !== '')
<div class="kt-row kt-oil-mi" style="font-size:{{ $fs }};font-weight:700;color:#111;line-height:1.1;">{{ $line }}</div>
@endif
