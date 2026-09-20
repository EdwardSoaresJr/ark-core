{{-- Paperwork email — attachment is the document authority file. Keep copy generic (customer, warranty, third party). --}}
<x-mail::message>
# {{ $document->type?->label() ?? 'Document' }} from {{ $shopName }}

{{ $shopName }} attached **{{ $document->title }}**.

@if ($repairOrder)
This paperwork is related to repair order **#{{ $repairOrder->repair_order_id }}**@if ($repairOrder->vehicle) · **{{ $repairOrder->vehicle->display_name }}**@endif.
@endif

The file is attached for your records.

@if (filled($staffNote))
**Note from the shop**

{{ $staffNote }}
@endif

Thanks,<br>
{{ $shopName }}
</x-mail::message>
