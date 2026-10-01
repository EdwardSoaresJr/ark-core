@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition $disposition */
    $disposition = $disposition ?? $concern->disposition;
@endphp

<span
    class="ops-disposition-select ops-disposition-select--readonly shrink-0"
    style="{{ $disposition->worksheetToneStyle() }}"
>{{ $disposition->label() }}</span>
