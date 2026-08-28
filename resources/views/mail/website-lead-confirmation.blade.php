<x-mail::customer-message>
# Request received

{{ $intro }}

@if (filled($responseHint))
**Typical response time:** {{ $responseHint }}
@endif

@if (filled($phoneDisplay))
Need immediate help? Call or text **{{ $phoneDisplay }}**.
@endif

Thanks,<br>
{{ $shopName }}
</x-mail::customer-message>
