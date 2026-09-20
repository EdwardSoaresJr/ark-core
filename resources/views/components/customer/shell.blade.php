@props([
    'layout' => 'stack',
    'indexable' => false,
    'title' => null,
    'showNav' => true,
    'showFooter' => true,
    'publicSurfacePage' => null,
    /** Full-bleed public page sections; inner content keeps customer-page-inset */
    'editorialSections' => false,
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],
])

@php
    $headerData = \App\Ark\Customer\CustomerSurfaceHeaderData::viewData();

    $mainClass = $layout === 'split'
        ? 'customer-shell__main customer-shell__main--split'
        : 'customer-shell__main';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">
        @if (! $indexable)
            <meta name="robots" content="noindex,nofollow,noarchive">
        @endif
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? \App\Support\Branding\Branding::tabTitle() }}</title>

        @isset($head)
            {{ $head }}
        @endisset

        @include('partials.branding._favicons')

        @include('partials.customer.theme-init')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">

        @vite($vite)
    </head>
    <body
        class="flex min-h-screen flex-col font-sans text-slate-950 antialiased public-surface"
        @if (filled($publicSurfacePage)) data-public-surface-page="{{ $publicSurfacePage }}" @endif
    >
        <div class="customer-shell flex flex-1 flex-col">
            @if ($showNav)
                @include('partials.customer.site-header', $headerData)
            @endif

            @include('partials.customer.breadcrumb-subheader')

            <main @class([
                $mainClass,
                'flex-1',
                'customer-shell__main--editorial' => $editorialSections,
                'customer-page-inset' => ! $editorialSections,
            ])>
                {{ $slot }}
            </main>

            @if ($showFooter)
                @include('partials.customer.site-footer', $headerData)
            @endif

            @stack('public-surface-footer')
        </div>

        <x-operations.image-lightbox />
    </body>
</html>
