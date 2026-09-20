@php
    /** @var \App\Ark\Operations\Printing\KeyTagPrintContext $ctx */
    $s = $size ?? 'md';
    $fs = match ($s) {
        'xl' => '18px',
        'lg' => '17px',
        'sm' => '15px',
        default => '17px',
    };
@endphp
<div class="kt-row kt-cust" style="font-weight:700;font-size:{{ $fs }};line-height:1.1;">{{ $ctx->customerName }}</div>
