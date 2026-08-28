@php
    /** @var list<array{label: string, href?: string}> $breadcrumb */
@endphp

<x-public.subheader-band>
    @include('partials.public.breadcrumb-trail', [
        'breadcrumb' => $breadcrumb,
    ])
</x-public.subheader-band>
