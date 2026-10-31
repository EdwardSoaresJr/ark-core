@php
    $ledgerHeadLabel = $ledgerHeadLabel ?? '';
@endphp

<div class="list-group-item ark-line-grid d-none d-md-grid py-2 subheader text-nowrap">
    <span>{{ $ledgerHeadLabel }}</span>
    <span class="text-end">Qty/Hrs</span>
    <span class="text-end">Price</span>
    <span class="text-end">Subtotal</span>
    <span class="text-end">Fees</span>
    <span class="text-end">Tax</span>
    <span class="text-end">Total</span>
</div>
