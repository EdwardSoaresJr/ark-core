@php
    /** @var \App\Ark\Operations\Printing\KeyTagPrintContext $ctx */
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '13px',
        'lg' => '12px',
        'sm' => '11px',
        default => '12px',
    };
@endphp
@if(trim($ctx->vehicleLine) !== '')
<div class="kt-row kt-veh" style="font-size:{{ $fs }};font-weight:400;color:#111;line-height:1.1;">{{ $ctx->vehicleLine }}</div>
@endif
