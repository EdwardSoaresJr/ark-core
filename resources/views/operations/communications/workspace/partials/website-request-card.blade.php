@php
    $fields = is_array($event['fields'] ?? null) ? $event['fields'] : [];
    $body = trim((string) ($event['body'] ?? ''));
@endphp

<article class="ops-comms-workspace__request" data-website-request="{{ $event['lead_id'] ?? '' }}">
    <p class="ops-comms-workspace__request-title">{{ $event['title'] ?? 'Website request' }}</p>
    @foreach ($fields as $field)
        @if (filled($field['value'] ?? null))
            <p class="ops-comms-workspace__request-line">
                <span class="ops-comms-workspace__request-label">{{ $field['label'] }}:</span>
                {{ $field['value'] }}
            </p>
        @endif
    @endforeach
    @if ($body !== '')
        <p class="ops-comms-workspace__request-line">
            <span class="ops-comms-workspace__request-label">{{ $event['body_label'] ?? 'Message' }}:</span>
        </p>
        <p class="ops-comms-workspace__request-body">{{ $body }}</p>
    @endif
    @if (filled($event['attribution'] ?? null))
        <p class="ops-comms-workspace__request-attribution">{{ $event['attribution'] }}</p>
    @endif
</article>
