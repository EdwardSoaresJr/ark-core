@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $ctx */
    $slot = $dueCombinedSlot ?? 'printed';
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '11px',
        'lg' => '10px',
        'sm' => '9px',
        default => '10px',
    };
    $line = null;
    if ($ctx->dueMileageAndDateLine !== null && $slot === 'printed') {
        $line = $ctx->dueMileageAndDateLine;
    } elseif ($ctx->dueMileageAndDateLine === null && trim($ctx->printedDateLine) !== '') {
        $line = $ctx->printedDateLine;
    }
@endphp
@if($line !== null && trim($line) !== '')
<div class="kt-row kt-oil-date" style="font-size:{{ $fs }};font-weight:600;color:#111;line-height:1.2;letter-spacing:0.02em;">{{ $line }}</div>
@endif
