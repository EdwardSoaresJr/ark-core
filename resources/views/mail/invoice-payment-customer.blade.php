<x-mail::customer-message :shop-name="$shopName">
# Pay your invoice balance

{{ $shopName }} has your **{{ $repairOrder->vehicle->display_name }}** ready for pickup (repair order #{{ $repairOrder->repair_order_id }}).

**Balance due:** {{ $balanceDueDisplay }}

Use the secure link below to pay online.

<x-mail::button :url="$portalUrl">
Pay balance online
</x-mail::button>

Thanks,<br>
{{ $shopName }}

<x-slot:subcopy>
If the button does not work, copy and paste this link into your browser: [{{ $portalUrl }}]({{ $portalUrl }})
</x-slot:subcopy>
</x-mail::customer-message>
