@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $ctx */
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '11px',
        'lg' => '10px',
        'sm' => '9px',
        default => '10px',
    };
    $line = $ctx->oilTypeLine;
@endphp
@if($line !== null && trim($line) !== '')
<div class="kt-row kt-oil-type" style="font-size:{{ $fs }};font-weight:600;color:#111;line-height:1.2;max-width:100%;padding:0 0.5mm;box-sizing:border-box;">{{ __('Oil: :type', ['type' => $line]) }}</div>
@endif
