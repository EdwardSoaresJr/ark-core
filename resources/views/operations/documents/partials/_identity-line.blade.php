<p class="muted">
    {{ $line['label'] }}: {{ $line['value'] }}
</p>
@if (filled($line['secondary_value'] ?? null))
    <p class="muted identity-address-locality">{{ $line['secondary_value'] }}</p>
@endif
