@php
    $ledgerHeadLabel = $ledgerHeadLabel ?? '';
@endphp

<div class="ops-worksheet-lines-head hidden gap-2 md:grid md:grid-cols-[minmax(0,1fr)_52px_78px_64px_64px_64px_88px]">
    <span class="ops-line-column-label">{{ $ledgerHeadLabel }}</span>
    <span class="ops-line-column-label text-right">Qty</span>
    <span class="ops-line-column-label text-right">Price</span>
    <span class="ops-line-column-label text-right">Subtotal</span>
    <span class="ops-line-column-label text-right">Fees</span>
    <span class="ops-line-column-label text-right">Tax</span>
    <span class="ops-line-column-label text-right">Total</span>
</div>
