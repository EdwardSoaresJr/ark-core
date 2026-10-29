@php
    $measurement = \App\Ark\Operations\Leads\Measurement\AppointmentRequestMeasurement::head();
@endphp
@if ($measurement !== null)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurement['ga4_id'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($measurement['ga4_id']), @json($measurement['ga4_config']));
        @if ($measurement['ads_id'])
            gtag('config', @json($measurement['ads_id']), {'send_page_view': false});
        @endif
    </script>
@endif
