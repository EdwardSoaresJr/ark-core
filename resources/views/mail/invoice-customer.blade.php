<x-mail::customer-message :shop-name="$shopName">
# Invoice from {{ $shopName }}

Your final invoice for **{{ $repairOrder->vehicle->display_name }}** (RO #{{ $repairOrder->repair_order_id }}) is attached.

**Balance due:** {{ $balanceDue }}

@if ($staffNote)
**Note from the shop:** {{ $staffNote }}
@endif

@if ($payUrl)
<x-mail::button :url="$payUrl">
Pay Invoice Online
</x-mail::button>

You can pay securely online with a credit or debit card.
@endif

Thank you for choosing {{ $shopName }}.
</x-mail::customer-message>
