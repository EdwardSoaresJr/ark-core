@if ($embed)
    @php($theme = \App\Ark\Customer\Theme\CustomerThemes::current())
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
            <meta name="robots" content="noindex,nofollow">
            <title>{{ $settings['title'] }} · {{ $shop->displayName() }}</title>
            @foreach ($theme->fontStylesheets() as $stylesheet)
                <link href="{{ $stylesheet }}" rel="stylesheet">
            @endforeach
            @vite(['resources/css/app.css'])
            <style data-customer-theme>{!! $theme->css() !!}</style>
            @if ($seo !== null)
                @include('website.partials.appointment-request-measurement')
            @endif
        </head>
        <body class="public-surface ark-booking-embed" data-customer-header="{{ $theme->header() }}">
            <main class="ark-booking ark-booking--embed">
                @include('booking.partials.form')
            </main>
            <script src="{{ asset('js/ark/ark-booking-form.js') }}?v={{ \App\Ark\Operations\Booking\Http\PublicBookingController::assetVersion() }}" defer></script>
        </body>
    </html>
@else
    <x-customer.shell :title="$settings['title'].' · '.$shop->displayName()" :indexable="$website !== null" public-surface-page="book">
        @if ($seo !== null)
            <x-slot:head>
                @include('website.seo', ['seo' => $seo, 'website' => $website])
            </x-slot:head>
        @endif
        <div class="ark-booking ark-booking--page">
            @include('booking.partials.form')
        </div>
        @push('public-surface-footer')
            <script src="{{ asset('js/ark/ark-booking-form.js') }}?v={{ \App\Ark\Operations\Booking\Http\PublicBookingController::assetVersion() }}" defer></script>
        @endpush
    </x-customer.shell>
@endif
