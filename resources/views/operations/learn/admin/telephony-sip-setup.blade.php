@php
    $communicationsSettings = route('operations.settings.shop.edit', ['section' => 'ark-cloud']);
@endphp

<div class="ops-learn-prose">
    <h3>Desk phones and voice transport</h3>
    <p>
        ARK owns floor behavior: who should ring, Calls Waiting, screen pop, and call-session history.
        Stock Core does not ship a carrier SIP/PSTN turnkey. Desk-phone registration, SIP domains, and
        carrier webhooks belong to a voice transport implementation — not a Settings paste form in Core.
    </p>

    <h3>What stays in ARK</h3>
    <ul>
        <li>Ring-group members and schedules in <a href="{{ $communicationsSettings }}">Settings → Communications</a></li>
        <li>Call session records and advisor ownership once a transport posts events into Core</li>
        <li>Shop display number for customer-facing identity</li>
    </ul>

    <h3>What this article does not cover</h3>
    <p>
        Carrier console steps, SIP credential lists, TwiML app setup, and webhook URL pasting are intentionally
        omitted from public Core documentation. Implement or connect a voice transport if the shop needs live PSTN.
    </p>
</div>
