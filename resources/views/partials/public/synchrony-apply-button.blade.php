@php
    $embedUrl = $embedUrl ?? ($trustSignals['financing']['synchrony_embed_url'] ?? null);
    $imageUrl = $imageUrl ?? ($trustSignals['financing']['synchrony_apply_button_image'] ?? null);
@endphp

@if (filled($embedUrl) && filled($imageUrl))
    <a
        href="{{ $embedUrl }}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-block"
    >
        <img
            src="{{ $imageUrl }}"
            alt="Apply now with Synchrony Car Care"
            width="218"
            height="auto"
            loading="lazy"
            class="block h-auto max-w-full"
        >
    </a>
@endif
