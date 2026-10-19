@php
    $appointmentRequestConversion = \App\Ark\Operations\Leads\Measurement\AppointmentRequestConversion::claim(request()->session());
@endphp
@if ($appointmentRequestConversion !== null)
    <script>
        gtag('event', 'generate_lead', @json($appointmentRequestConversion['generate_lead']));
        @if ($appointmentRequestConversion['ads'] !== null)
            gtag('event', 'conversion', @json($appointmentRequestConversion['ads']));
        @endif
    </script>
@endif
