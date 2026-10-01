@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $ctx */
    $slot = $dueCombinedSlot ?? 'next';
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '13px',
        'lg' => '12px',
        'sm' => '11px',
        default => '12px',
    };
    $line = null;
    if ($ctx->dueMileageAndDateLine !== null && $slot === 'next') {
        $line = $ctx->dueMileageAndDateLine;
    } elseif ($ctx->dueMileageAndDateLine === null) {
        $line = $ctx->nextOilDueLine;
    }
@endphp
@if($line !== null && $line !== '')
<div class="kt-row kt-oil-next" style="font-size:{{ $fs }};font-weight:600;color:#111;line-height:1.15;">{{ $line }}</div>
@endif
