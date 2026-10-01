@php
    /** Optional left-cell label (repair title). Empty keeps numeric columns aligned. */
    $lineHeadLabel = $lineHeadLabel ?? '';
@endphp
<div class="line-head">
    <span class="line-head-work">{{ $lineHeadLabel }}</span>
    <span class="numeric">Qty</span>
    <span class="numeric">Price</span>
    <span class="numeric">Subtotal</span>
    <span class="numeric">Fees</span>
    <span class="numeric">Tax</span>
    <span class="numeric">Total</span>
</div>
