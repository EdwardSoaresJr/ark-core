@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $ctx */
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '12px',
        'lg' => '11px',
        'sm' => '10px',
        default => '11px',
    };
@endphp
@if(trim($ctx->shopLine) !== '')
<div class="kt-row kt-oil-shop" style="font-weight:700;font-size:{{ $fs }};letter-spacing:0.4px;color:#111;line-height:1.1;">{{ $ctx->shopLine }}</div>
@endif
