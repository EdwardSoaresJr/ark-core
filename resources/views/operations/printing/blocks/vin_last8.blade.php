@php
    /** @var \App\Ark\Operations\Printing\KeyTagPrintContext $ctx */
    $line = $ctx->vinLineForKeyTag();
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '11px',
        'lg' => '10px',
        'sm' => '9px',
        default => '10px',
    };
    if ($ctx->vinDisplayMode === 'full' && $line !== null && strlen($line) > 14) {
        $fs = match ($s) {
            'xl' => '11px',
            'lg' => '10px',
            'sm' => '9px',
            default => '10px',
        };
    }
@endphp
@if($line !== null && $line !== '')
<div class="kt-row kt-vin" style="font-size:{{ $fs }};font-weight:400;color:#333;opacity:0.9;word-break:break-all;line-height:1.1;">{{ $line }}</div>
@endif
