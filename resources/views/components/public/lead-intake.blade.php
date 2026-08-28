@props([
    'shop' => null,
    'title' => null,
    'seo' => null,
    'publicSurfacePage' => 'homepage',
    'indexable' => true,
    'editorialSections' => false,
])

@php
    $seo ??= app(\App\Ark\Growth\Seo\SeoEngine::class)->forHomepage()->toArray();

    $vite = [
        'resources/css/app.css',
        'resources/js/public-lead-phone.js',
        'resources/js/public-mobile-contact-bar.js',
    ];

    // Register Alpine.data before app.js starts Alpine (alpine:init).
    if ($publicSurfacePage === 'book') {
        $vite[] = 'resources/js/public-book-wizard.js';
    }

    $vite[] = 'resources/js/app.js';

    if (\App\Ark\Operations\Leads\Public\PublicSurfaceSettings::instrumentationEnabled()) {
        $vite[] = 'resources/js/public-surface-events.js';
    }
@endphp

<x-customer.shell
    :indexable="$indexable"
    :title="$title ?? $seo['title']"
    :public-surface-page="$publicSurfacePage"
    :editorial-sections="$editorialSections"
    :vite="$vite"
>
    <x-slot:head>
        @include('partials.public.seo', ['seo' => $seo])
        @include('partials.public.google-ads-tag')

        @if (\App\Ark\Operations\Leads\Public\PublicSurfaceSettings::instrumentationEnabled())
            <script>
                window.__publicSurfaceEvents = {
                    endpoint: @json(route('public.surface-events.store')),
                    csrf: @json(csrf_token()),
                };
            </script>
        @endif
    </x-slot:head>

    @push('public-surface-footer')
        @include('partials.public.mobile-sticky-contact-bar')
    @endpush

    {{ $slot }}
</x-customer.shell>
