@php
    use App\Ark\Operations\Booking\BookingMode;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;
    use App\Ark\Operations\PhoneNumber;

    $shop = \App\Ark\Operations\Settings\ShopSettings::current();
    $booked = $bookingRequest?->mode === BookingMode::Instant && $bookingRequest->appointment_id !== null;
    $heading = $booked ? 'Your appointment is booked' : 'Request received';
    $message = $booked ? $settings['instant_confirmation'] : $settings['request_confirmation'];
    $phone = $shop->phone ?? null;
    $phoneHref = filled($phone) ? preg_replace('/\D+/', '', (string) $phone) : null;
@endphp


@if ($embed)
    @php($theme = \App\Ark\Customer\Theme\CustomerThemes::current())
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
            <meta name="robots" content="noindex,nofollow">
            <title>{{ $heading }} · {{ $shop->displayName() }}</title>
            @foreach ($theme->fontStylesheets() as $stylesheet)
                <link href="{{ $stylesheet }}" rel="stylesheet">
            @endforeach
            @vite(['resources/css/app.css'])
            <style data-customer-theme>{!! $theme->css() !!}</style>
            @if ($seo !== null)
                @include('website.partials.appointment-request-measurement')
            @endif
        </head>
        <body class="public-surface ark-booking-embed">
            <main class="ark-booking ark-booking--embed">@include('booking.partials.receipt-card')</main>
            <script src="{{ asset('js/ark/ark-booking-form.js') }}?v={{ \App\Ark\Operations\Booking\Http\PublicBookingController::assetVersion() }}" defer></script>
        </body>
    </html>
@else
    <x-customer.shell :title="$heading.' · '.$shop->displayName()" public-surface-page="book">
        @if ($seo !== null)
            <x-slot:head>
                @include('website.partials.appointment-request-measurement')
            </x-slot:head>
        @endif
        <div class="ark-booking ark-booking--page">@include('booking.partials.receipt-card')</div>
        @push('public-surface-footer')
            <script src="{{ asset('js/ark/ark-booking-form.js') }}?v={{ \App\Ark\Operations\Booking\Http\PublicBookingController::assetVersion() }}" defer></script>
        @endpush
    </x-customer.shell>
@endif
