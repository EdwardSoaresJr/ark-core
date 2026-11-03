@php
    $publicNotice = config('website.public_notice');
@endphp
@if (is_array($publicNotice) && filled($publicNotice['text'] ?? null))
    <div class="ark-public-notice" role="note" style="background:#0f172a;color:#f8fafc;font:14px/1.45 ui-sans-serif,system-ui,-apple-system,sans-serif;padding:9px 16px;text-align:center">
        {{ $publicNotice['text'] }}
        @if (filled($publicNotice['link_url'] ?? null) && filled($publicNotice['link_label'] ?? null))
            <a href="{{ $publicNotice['link_url'] }}" style="color:#7dd3fc;font-weight:600;text-decoration:underline">{{ $publicNotice['link_label'] }}</a>
        @endif
    </div>
@endif
