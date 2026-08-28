@php
    $communicationsSettings = route('operations.settings.shop.edit', ['section' => 'communications']);
    $voiceWebhook = route('webhooks.communications.twilio.voice.incoming');
    $sipOutboundWebhook = route('webhooks.communications.twilio.voice.sip-outbound');
    $statusWebhook = route('webhooks.communications.twilio.voice.status');
    $messagingWebhook = route('webhooks.communications.twilio.messaging.incoming');
@endphp

<div class="ops-learn-prose">
    <h3>What you are setting up</h3>
    <p>ARK Communications connects your shop phone line to the workboard: inbound calls ring your team, SMS lands in ARK, and advisors get screen pop with customer context. Twilio carries the call; ARK decides <strong>who rings</strong> and tracks Calls Waiting ownership.</p>
    <p>Operational panel: <a href="{{ $communicationsSettings }}">Settings → Communications</a>. Health boxes should read green when deployment and Twilio Console are aligned.</p>

    <h3>Owner checklist (in order)</h3>
    <ol>
        <li><strong>Server secrets</strong> — Twilio credentials in production <code>.env</code> (see below).</li>
        <li><strong>Shop number</strong> — save your Twilio phone number in Settings → Communications.</li>
        <li><strong>Twilio phone number webhooks</strong> — voice + SMS URLs pasted in Twilio Console (not in ARK).</li>
        <li><strong>Ring group</strong> — cell numbers and optional SIP desk phones in Settings (not <code>.env</code>).</li>
        <li><strong>Screen pop</strong> — Reverb running on production if you want live incoming-call banners.</li>
        <li><strong>Verify</strong> — health boxes green, test incoming call, answer on a ring target.</li>
    </ol>

    <h3>Step 1 — Twilio credentials (Settings)</h3>
    <p><a href="{{ $communicationsSettings }}">Settings → Communications → General</a> → Twilio Account SID and Auth Token. Secrets are encrypted in the shop database.</p>
    <p>Server <code>.env</code> (<code>TWILIO_ACCOUNT_SID</code>, <code>TWILIO_AUTH_TOKEN</code>) still works as a fallback for existing deploys — save in Settings to move off SSH editing.</p>
    <p>Screen pop also needs Reverb keys in server <code>.env</code> (<code>BROADCAST_CONNECTION=reverb</code>, <code>REVERB_*</code>).</p>

    <h3>Step 2 — Shop number in ARK</h3>
    <p><a href="{{ $communicationsSettings }}">Settings → Communications</a> → save the Twilio number customers dial. This is caller ID for outbound SIP calls and display in health summary.</p>

    <h3>Step 3 — Twilio phone number webhooks (customer calls &amp; SMS)</h3>
    <p><strong>Twilio → Phone Numbers → Active Numbers → your shop number → Configure</strong></p>
    <p>Copy URLs from Settings → Communications (click-to-copy) or use these:</p>
    <ul>
        <li><strong>A call comes in</strong> — Webhook · HTTP POST · <code>{{ $voiceWebhook }}</code></li>
        <li><strong>Call status changes</strong> — optional on the number; ARK also sets callbacks on ring-group dials → <code>{{ $statusWebhook }}</code></li>
        <li><strong>Messaging</strong> — incoming SMS/MMS → <code>{{ $messagingWebhook }}</code></li>
    </ul>
    <p><strong>Important:</strong> Shop inbound uses the <strong>phone number</strong> Voice URL — not the SIP domain Call Control form.</p>

    <h3>Step 4 — Call routing (who rings)</h3>
    <p><a href="{{ $communicationsSettings }}">Settings → Communications → Call routing</a></p>
    <ul>
        <li><strong>Cell</strong> — E.164 number (<code>+1…</code>). Link a staff owner when possible for automatic floor assignment.</li>
        <li><strong>SIP</strong> — desk phone endpoint, e.g. <code>sip:101@your-domain.sip.us1.twilio.com</code></li>
        <li><strong>Enabled</strong> — only checked endpoints ring. All enabled targets ring at once; first answer wins.</li>
        <li><strong>Ring schedule</strong> — optional business-hours gating per endpoint.</li>
    </ul>
    <p>Ring targets live in the database. Do not put forward numbers in <code>.env</code>.</p>

    <h3>Step 5 — Desk phones</h3>
    <p>Poly VVX and other SIP desk phones register to a Twilio <strong>SIP domain</strong> and ring from the ARK ring group alongside cell numbers. Admin setup: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']) }}">Telephony and SIP desk phones</a>.</p>
    <p>Customer calls always hit the Twilio phone number first — desk phones are ring targets, not a separate inbound path. SMS and MMS always use the Messaging webhook on the shop number.</p>

    <h3>Step 6 — Call flow options</h3>
    <p>Settings → Communications also controls:</p>
    <ul>
        <li><strong>Voicemail</strong> — when no one answers</li>
        <li><strong>Business hours</strong> — after-hours behavior</li>
        <li><strong>Advisor comms accountability</strong> — block other ARK pages until Work comms queue is cleared (Mark Handled / Mark Read / Reply)</li>
        <li><strong>Escalation SMS</strong> — text all active advisors when calls or texts stay unhandled (delay + cooldown configurable)</li>
        <li><strong>Simulate incoming call</strong> — dry-run without a real customer call</li>
    </ul>

    <h3>Health boxes — what green means</h3>
    <dl>
        <dt>Provider</dt>
        <dd>Twilio configured and telephony enabled.</dd>
        <dt>Connection</dt>
        <dd>Account SID + auth token present and valid enough for ARK to connect.</dd>
        <dt>Voice webhook</dt>
        <dd>Recent inbound voice webhook received (proves Twilio → ARK path).</dd>
        <dt>SMS webhook</dt>
        <dd>Recent messaging webhook received.</dd>
        <dt>Screen pop</dt>
        <dd>Reverb reachable for live incoming-call and inbound-SMS broadcasts (Customer Hub Comms also listens).</dd>
    </dl>

    <h3>Verify before you trust it</h3>
    <ol>
        <li>Settings → Communications — Provider, Connection, Voice webhook healthy.</li>
        <li>Ring group has at least one enabled cell or SIP endpoint.</li>
        <li><strong>Test incoming call</strong> from Settings — your phone rings.</li>
        <li>Answer — Calls Waiting shows the call; owner assigns if configured.</li>
        <li>Send a test SMS to the shop number — message appears in ARK messaging.</li>
        <li>If using desk phones — SIP domain shows <strong>Registered</strong>; outbound dial connects with shop caller ID.</li>
    </ol>

    <h3>Facebook Messenger (optional)</h3>
    <p>Customers who message your Facebook Page can land in the same ARK conversation timeline as SMS. This is a separate Meta app + Page webhook — not Twilio.</p>
    <p>Owner does not need to wire Meta consoles daily; an admin follows <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'messenger-setup']) }}">Facebook Messenger setup</a> once, then verifies with a test Page message.</p>

    <h3>Common owner mistakes</h3>
    <ul>
        <li><strong>Webhook on wrong Twilio object</strong> — inbound customer calls → phone number; desk outbound → SIP domain Call Control.</li>
        <li><strong>Empty ring group</strong> — Twilio accepts the call but nobody rings.</li>
        <li><strong>Secrets only in Settings</strong> — Account SID and Auth Token belong in Settings → Communications, not scattered in <code>.env</code> (env is legacy fallback only).</li>
        <li><strong>Legacy forward env vars</strong> — remove old <code>TELEPHONY_FORWARD_*</code> style vars; ring group is authoritative.</li>
        <li><strong>Screen pop “red” but calls work</strong> — fix Reverb, not Twilio voice webhooks.</li>
    </ul>

    <p>Card payments: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'square-payments-setup']) }}">Square payments setup</a>.</p>
</div>
