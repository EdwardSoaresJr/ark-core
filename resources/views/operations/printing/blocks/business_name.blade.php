@php
    /** @var \App\Ark\Operations\Printing\KeyTagPrintContext $ctx */
    $biz = trim((string) ($ctx->businessNameLine ?? ''));
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '12px',
        'lg' => '11px',
        'sm' => '10px',
        default => '11px',
    };
@endphp
@if($biz !== '')
<div class="kt-row kt-business" style="font-weight:700;font-size:{{ $fs }};letter-spacing:0.4px;line-height:1.1;">{{ $biz }}</div>
@endif
