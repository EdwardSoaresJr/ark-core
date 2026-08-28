@php
    $messengerSettings = route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => 'messenger']);
    $webhookUrl = route('webhooks.communications.meta.messenger');
@endphp

<div class="ops-learn-prose">
    <p>Settings panel: <a href="{{ $messengerSettings }}">Settings → Communications → Messenger</a>. Messenger threads land in the same conversation timeline as SMS — not a separate inbox.</p>

    <h3>What ARK does (and does not do)</h3>
    <ul>
        <li><strong>Inbound</strong> — Meta posts to ARK’s webhook → message appears in Comms queue and Customer Hub.</li>
        <li><strong>Outbound</strong> — advisors reply from Quick Reply on Customer Hub or the RO rail (same composer as SMS).</li>
        <li><strong>Linking</strong> — unknown PSIDs can be linked to a customer record; matched PSIDs auto-resolve.</li>
        <li><strong>Not included</strong> — ARK does not manage Meta ad leads, comment moderation, or Instagram DMs in this path. This is Page Messenger webhook ingress only.</li>
    </ul>

    <h3>Prerequisites</h3>
    <ol>
        <li>Facebook <strong>Page</strong> for the shop (customers message the Page).</li>
        <li>Meta <strong>Business</strong> portfolio with admin access to that Page.</li>
        <li>Meta <strong>App</strong> (type: Business) with <strong>Messenger</strong> and <strong>Webhooks</strong> products added.</li>
        <li>Production site reachable at <code>https://</code> — Meta cannot verify localhost webhooks.</li>
    </ol>
    <p>
        Meta consoles:
        <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">My Apps</a>
        ·
        <a href="https://developers.facebook.com/docs/messenger-platform/getting-started" target="_blank" rel="noopener noreferrer">Messenger Platform docs</a>
        ·
        <a href="https://developers.facebook.com/docs/messenger-platform/send-messages" target="_blank" rel="noopener noreferrer">Send API (24-hour window)</a>
    </p>

    <h3 id="setup-sequence">Setup sequence (in order)</h3>
    <ol>
        <li>In ARK, open <a href="{{ $messengerSettings }}">Messenger settings</a>. Copy the generated <strong>verify token</strong> (or click Regenerate), save, then paste the same string into Meta.</li>
        <li>Copy the <strong>webhook URL</strong> from ARK: <code>{{ $webhookUrl }}</code></li>
        <li>Meta App → <strong>Webhooks</strong> → Page → <strong>Add subscription</strong>:
            <ul>
                <li><strong>Callback URL</strong> — paste ARK webhook URL.</li>
                <li><strong>Verify token</strong> — must match ARK exactly.</li>
                <li><strong>Fields</strong> — subscribe at minimum to <code>messages</code>. Recommended: <code>message_deliveries</code> and <code>message_reads</code> for delivery/read receipts.</li>
            </ul>
        </li>
        <li>Meta App → <strong>Messenger</strong> → Settings → connect your Facebook Page to the app.</li>
        <li>Generate a <strong>Page access token</strong> for that Page (requires <code>pages_messaging</code>). Use a long-lived token in production.</li>
        <li>Meta App → <strong>Settings → Basic</strong> → copy <strong>App secret</strong>.</li>
        <li>Back in ARK: enter Page ID, Page access token, verify token, app secret. Check <strong>Enable Messenger ingress and queue</strong>. Save.</li>
        <li>Send a test message to the Page from a personal Facebook account. ARK webhook status should move from <em>Waiting for first message</em> to <em>Healthy</em>.</li>
    </ol>

    <h3 id="page-access-token">Page ID and access token</h3>
    <p><strong>Page ID</strong> — Meta App → Messenger → Settings shows connected Pages and IDs. Must match the Page customers message.</p>
    <p><strong>Page access token</strong> — generate in the Messenger product for the connected Page. Token must include <code>pages_messaging</code>. If outbound sends fail with OAuth errors, regenerate the token and re-save in ARK (leave blank in the password field keeps the saved token).</p>
    <p>ARK stores the token encrypted in shop settings — not in <code>.env</code>.</p>

    <h3 id="app-secret">App secret and webhook security</h3>
    <p>Meta signs POST webhooks with your app secret. ARK rejects unsigned or tampered payloads when Messenger is enabled.</p>
    <p>Save app secret in ARK before going live. Rotating the secret in Meta requires updating ARK the same day.</p>

    <h3 id="24-hour-window">24-hour window and message tags</h3>
    <p>Meta allows free-form replies for 24 hours after the customer’s last message. After that, outbound sends require an approved <strong>message tag</strong> or a customer-initiated message to reopen the window.</p>
    <p>ARK’s <strong>Default outside-window message tag</strong> applies when advisors reply after the window expires. Pick the tag that matches shop policy — appointment updates, post-service follow-up, etc. Meta policy still governs allowed content.</p>
    <p>Advisors can override the tag per send in Quick Reply when the composer detects an expired window.</p>
    <p>Floor guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a> (same composer discipline for SMS and Messenger).</p>

    <h3>Verify and troubleshoot</h3>
    <ul>
        <li>ARK webhook label <strong>Healthy</strong> after first inbound message.</li>
        <li><strong>Incomplete setup</strong> — missing Page ID, token, verify token, or app secret while enabled.</li>
        <li>Meta verify fails — token mismatch or callback URL not publicly reachable.</li>
        <li>Inbound works, outbound fails — Page token expired or missing <code>pages_messaging</code>.</li>
        <li>Message in Meta inbox but not in ARK — webhook subscription missing <code>messages</code> field or wrong Page connected to app.</li>
    </ul>
    <p>Weekly comms hygiene: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'comms-health-check']) }}">Communications health check</a>.</p>

    <h3>Related guides</h3>
    <p>Twilio phone/SMS: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Communications setup</a>.</p>
    <p>Advisor queue: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue</a>.</p>
</div>
