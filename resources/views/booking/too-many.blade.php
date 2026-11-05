@php($shop = \App\Ark\Operations\Settings\ShopSettings::current())

@if ($embed)
    @php($theme = \App\Ark\Customer\Theme\CustomerThemes::current())
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
            <meta name="robots" content="noindex,nofollow">
            <title>Too many tries · {{ $shop->displayName() }}</title>
            @vite(['resources/css/app.css'])
            <style data-customer-theme>{!! $theme->css() !!}</style>
        </head>
        <body class="public-surface ark-booking-embed">
            <main class="ark-booking ark-booking--embed">@include('booking.partials.too-many-card')</main>
        </body>
    </html>
@else
    <x-customer.shell :title="'Too many tries · '.$shop->displayName()" public-surface-page="book">
        <div class="ark-booking ark-booking--page">@include('booking.partials.too-many-card')</div>
    </x-customer.shell>
@endif
