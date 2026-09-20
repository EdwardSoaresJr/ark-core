<x-mail::message>
# Inspection walk ready

Hi {{ $recipientName }},

Open the vehicle inspection walk for **{{ $vehicleLabel }}** ({{ $repairOrderLabel }}).

<x-mail::button :url="$walkUrl">
Open Inspection Walk
</x-mail::button>

{{ $shopName }}

<x-slot:subcopy>
Walk link: [{{ $walkUrl }}]({{ $walkUrl }})
</x-slot:subcopy>
</x-mail::message>
