@php
    use App\Ark\Operations\Leads\Public\WisetackMerchantMarketing;

    $prequalUrl = $prequalUrl ?? ($trustSignals['financing']['wisetack_url'] ?? null);
    $label = $label ?? WisetackMerchantMarketing::PREQUAL_BUTTON_LABEL;
@endphp

@if (filled($prequalUrl))
    <a
        href="{{ $prequalUrl }}"
        target="_blank"
        rel="noopener noreferrer"
        class="public-wisetack-prequal-button"
    >
        {{ $label }}
    </a>
@endif
