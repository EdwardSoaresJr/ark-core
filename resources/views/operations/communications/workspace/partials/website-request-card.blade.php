@php
    $fields = is_array($event['fields'] ?? null) ? $event['fields'] : [];
    $body = trim((string) ($event['body'] ?? ''));
@endphp

<article class="card card-sm" data-website-request="{{ $event['lead_id'] ?? '' }}">
    <div class="card-body">
        <div class="fw-bold mb-1">{{ $event['title'] ?? 'Website request' }}</div>
        @foreach ($fields as $field)
            @if (filled($field['value'] ?? null))
                <div>
                    <span class="text-secondary">{{ $field['label'] }}:</span>
                    {{ $field['value'] }}
                </div>
            @endif
        @endforeach
        @if ($body !== '')
            <div class="mt-1">
                <span class="text-secondary">{{ $event['body_label'] ?? 'Message' }}:</span>
            </div>
            <p class="mb-0" style="white-space: pre-wrap">{{ $body }}</p>
        @endif
        @if (filled($event['attribution'] ?? null))
            <div class="mt-1 small text-secondary">{{ $event['attribution'] }}</div>
        @endif
    </div>
</article>
