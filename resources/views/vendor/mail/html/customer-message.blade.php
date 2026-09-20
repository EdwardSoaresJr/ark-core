@php
    $shopName = $shopName ?? \App\Support\Mail\ShopMailBranding::shopName();
    $shopLogoUrl = $shopLogoUrl ?? \App\Support\Mail\ShopMailBranding::logoUrl();
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::shop-header :url="config('app.url')" :shop-name="$shopName" :logo-url="$shopLogoUrl" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $shopName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
