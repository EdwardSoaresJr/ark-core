@php
    $title = $seo['title'] ?? '';
    $description = $seo['description'] ?? '';
    $canonical = $seo['canonical'] ?? url()->current();
    $shopName = $website?->shopName() ?? ($seo['shop_name'] ?? '');
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'AutoRepair',
        'name' => $shopName,
        'url' => $canonical,
    ];
    if ($website instanceof \App\Ark\Website\PublishedWebsite) {
        if ($website->phone() !== '') {
            $schema['telephone'] = $website->phoneDisplay();
        }
        if ($website->address() !== '') {
            $schema['address'] = $website->address();
        }
    }
@endphp
<link rel="canonical" href="{{ $canonical }}">
@if ($description !== '')
    <meta name="description" content="{{ $description }}">
@endif
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title }}">
@if ($description !== '')
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
