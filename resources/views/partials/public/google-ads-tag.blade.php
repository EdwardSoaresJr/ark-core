@php
    $googleAdsTagId = \App\Ark\Growth\Public\GoogleAdsTag::tagId();
    $googleAdsGa4Id = \App\Ark\Growth\Public\GoogleAdsTag::ga4MeasurementId();
@endphp

@if ($googleAdsTagId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAdsTagId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @json($googleAdsTagId));
        @if ($googleAdsGa4Id)
            gtag('config', @json($googleAdsGa4Id));
        @endif
    </script>
@endif
