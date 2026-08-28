<x-mail::message>
# Your service estimate

{{ $shopName }} prepared an estimate for your **{{ $repairOrder->vehicle->display_name }}** (repair order #{{ $repairOrder->repair_order_id }}).

**Estimate total:** {{ $totals->format($totals->totalCents()) }}

Review your estimate online to approve or defer recommended work. A PDF copy is attached for your records.

<x-mail::button :url="$portalUrl">
Review and authorize estimate
</x-mail::button>

@if (filled($staffNote))
**Note from your advisor**

{{ $staffNote }}
@endif

Thanks,<br>
{{ $shopName }}

<x-slot:subcopy>
If the button does not work, copy and paste this link into your browser: [{{ $portalUrl }}]({{ $portalUrl }})
</x-slot:subcopy>
</x-mail::message>
