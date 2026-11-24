@props(['title' => 'Vehicle Inspection'])

@php
    $accentHtml = auth()->user()?->accentHtmlAttributes() ?? ['data-accent' => 'ark2'];
    $displayTheme = auth()->user()?->displayTheme()->value ?? 'system';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" @foreach ($accentHtml as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="ark-ui-tenant-key" content="{{ \App\Ark\Runtime\Preferences\UiPreferenceScope::tenantKey(request()) }}">
    @include('partials.operations.display-theme-boot', ['displayTheme' => $displayTheme])
    <title>{{ $title }}</title>
    @include('partials.branding._favicons')
    @fonts
    @include('partials.shop-channel')
    @vite(['resources/css/app.css', 'resources/css/tabler.css', 'resources/js/app.js', 'resources/js/tabler.js'])
</head>
<body>
    <div class="page" data-inspection-tablet-shell>
        <div class="page-wrapper">
            <main>
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-operations.image-lightbox ui="tabler" />
</body>
</html>
