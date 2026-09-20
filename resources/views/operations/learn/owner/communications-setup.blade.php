@php
    $communicationsSettings = route('operations.settings.shop.edit', ['section' => 'ark-cloud']);
@endphp

<div class="ops-learn-prose">
    <h3>What Communications covers in ARK</h3>
    <p>
        ARK keeps the shop’s conversation history, call sessions, ring-group intent, and advisor accountability
        in Core. Sending and receiving SMS or placing carrier voice calls requires a <strong>messaging / voice transport</strong>
        beyond stock Core.
    </p>
    <p>
        Stock ARK Core does not include a paste-credentials carrier setup. When a transport is not configured,
        outbound SMS and live voice stay unavailable with an honest message — repair orders, customers, and
        estimates keep working.
    </p>

    <h3>What you can do in Settings today</h3>
    <p>Open <a href="{{ $communicationsSettings }}">Settings → Communications</a> to review:</p>
    <ul>
        <li>Shop phone number shown to customers (identity / caller ID display)</li>
        <li>Ring-group membership and schedules (who should answer when voice is connected)</li>
        <li>Voicemail / after-hours intent and advisor accountability options</li>
        <li>Conversation and call history already stored in ARK</li>
    </ul>

    <h3>Mail</h3>
    <p>
        Customer email uses <strong>ARK Email</strong> when the shop is paired with ARK Platform.
        There is no shop-owned commercial mail-token form in stock Core.
    </p>

    <h3>Adding SMS or voice later</h3>
    <p>
        A developer can implement Core’s outbound SMS and telephony contracts, or use managed ARK services
        when those products are available for the shop. This guide does not include carrier console recipes.
    </p>
</div>
