@props([
    'role',
    'article',
    'file',
    'alt',
    'caption' => null,
    'wide' => false,
])

@php
    use App\Ark\Operations\Learn\LearnArkMedia;

    $src = LearnArkMedia::image($role, $article, $file);
    $hasImage = LearnArkMedia::imageExists($role, $article, $file);
@endphp

<figure @class(['ops-learn-figure', 'ops-learn-figure--wide' => $wide])>
    @if ($hasImage)
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            class="ops-learn-figure__image"
            loading="lazy"
        >
    @else
        <div class="ops-learn-figure__placeholder" role="img" aria-label="{{ $alt }}">
            <span class="ops-learn-figure__placeholder-label">Screenshot</span>
            <span class="ops-learn-figure__placeholder-path">{{ $role }}/{{ $article }}/{{ $file }}</span>
        </div>
    @endif
    @if ($caption)
        <figcaption class="ops-learn-figure__caption">{{ $caption }}</figcaption>
    @endif
</figure>
