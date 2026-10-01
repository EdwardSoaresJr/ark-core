@php
    /** @var \App\Ark\Operations\Printing\KeyTagPrintContext $ctx */
    $line = $ctx->licensePlateLine !== null ? trim((string) $ctx->licensePlateLine) : '';
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '12px',
        'lg' => '11px',
        'sm' => '10px',
        default => '11px',
    };
@endphp
@if($line !== '')
<div class="kt-row kt-plate" style="font-size:{{ $fs }};font-weight:500;color:#222;letter-spacing:0.25px;line-height:1.1;">{{ $line }}</div>
@endif
