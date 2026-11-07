@php
    $publicNotice = config('website.public_notice');
@endphp
@if (is_array($publicNotice) && filled($publicNotice['text'] ?? null))
    <div class="ark-public-notice" role="note">
        <p class="ark-public-notice__text">
            {{ $publicNotice['text'] }}
            @if (filled($publicNotice['link_url'] ?? null) && filled($publicNotice['link_label'] ?? null))
                <a class="ark-public-notice__link" href="{{ $publicNotice['link_url'] }}">{{ $publicNotice['link_label'] }}</a>
            @endif
        </p>
    </div>
@endif
