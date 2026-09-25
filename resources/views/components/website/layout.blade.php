@props([
    'seo',
    'website' => null,
    'indexable' => true,
    'page' => 'home',
])

<x-customer.shell
    :indexable="$indexable"
    :title="$seo['title']"
    :public-surface-page="$page"
    :editorial-sections="true"
>
    <x-slot:head>
        @include('website.seo', ['seo' => $seo, 'website' => $website])
    </x-slot:head>

    {{ $slot }}
</x-customer.shell>
