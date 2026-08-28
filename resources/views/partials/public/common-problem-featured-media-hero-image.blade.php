@php
    $width = (int) ($featuredMedia['width'] ?? 0);
    $height = (int) ($featuredMedia['height'] ?? 0);
@endphp

<img
    src="{{ $featuredMedia['url'] }}"
    alt="{{ $featuredMedia['alt'] }}"
    class="public-featured-media__image"
    loading="eager"
    fetchpriority="high"
    decoding="async"
    @if ($width > 0 && $height > 0) width="{{ $width }}" height="{{ $height }}" @endif
>
