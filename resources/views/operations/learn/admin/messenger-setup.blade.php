@php
    $messengerSettings = route('operations.settings.shop.edit', ['section' => 'ark-cloud']);
@endphp

<div class="ops-learn-prose">
    <p>Settings panel: <a href="{{ $messengerSettings }}">Settings → Communications → Messenger</a>. Messenger appears as a conversation channel in ARK alongside SMS.</p>

    <h3>Core behavior</h3>
    <ul>
        <li><strong>Channel</strong> — Messenger conversations use the same Customer Hub and RO communication timeline as SMS.</li>
        <li><strong>Linking</strong> — unknown PSIDs can be linked to a customer record; matched PSIDs auto-resolve.</li>
        <li><strong>Outbound</strong> — Core does not ship Meta Messenger transport. Outbound replies return <em>Messenger outbound is not configured.</em></li>
        <li><strong>Inbound</strong> — Core does not ship Meta webhook ingress. Historical Messenger messages remain readable when already linked.</li>
    </ul>

    <h3 id="24-hour-window">24-hour window and message tags</h3>
    <p>When Messenger transport is configured by your deployment, Meta allows free-form replies for 24 hours after the customer’s last message. Outside that window, outbound sends require an approved message tag.</p>
    <p>ARK stores a default outside-window tag in Messenger settings for future transport wiring.</p>
    <p>Floor guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a> (same composer discipline for SMS and Messenger).</p>

    <h3>Related guides</h3>
    <p>Twilio phone/SMS: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Communications setup</a>.</p>
    <p>Advisor queue: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue</a>.</p>
</div>
