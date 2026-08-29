@php
    $voiceWebhook = route('webhooks.communications.twilio.voice.incoming');
    $sipOutboundWebhook = route('webhooks.communications.twilio.voice.sip-outbound');
    $statusWebhook = route('webhooks.communications.twilio.voice.status');
    $messagingWebhook = route('webhooks.communications.twilio.messaging.incoming');
    $communicationsSettings = route('operations.settings.shop.edit', ['section' => 'communications']);
@endphp

<div class="ops-learn-prose">
    <p>Owner checklist (Twilio env, webhooks, ring group): <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Communications setup</a>. This article is the <strong>Twilio SIP domain</strong> desk-phone deep dive for Poly VVX and other SIP phones.</p>

    <h3>What ARK does (and does not do)</h3>
    <p>ARK is <strong>not a PBX admin product</strong>. Twilio carries voice and SMS. ARK decides <strong>who rings</strong>, owns Calls Waiting, screen pop, floor ownership, and mobile projections — without rebuilding a phone company inside ARK.</p>
    <ul>
        <li><strong>Shop inbound</strong> — customer dials your Twilio phone number → Twilio posts to ARK → ARK returns TwiML that rings every enabled ring-group endpoint at once (cells + SIP). First answer wins.</li>
        <li><strong>SIP desk phones</strong> — phones register to a Twilio <strong>SIP domain</strong>. ARK rings them with <code>sip:extension@your-domain.sip.us1.twilio.com</code> in the ring group. Outbound dials from the desk phone hit ARK’s SIP outbound webhook and connect with the shop caller ID.</li>
        <li><strong>Ring targets live in the database</strong> — Settings → Communications → Ring group. Do not put forward numbers in <code>.env</code>.</li>
    </ul>

    <h3>Two different Twilio products — do not mix them up</h3>
    <p><strong>Use Programmable Voice SIP domains.</strong> Do <strong>not</strong> use Elastic SIP Trunking for ARK desk phones.</p>
    <ul>
        <li><strong>Wrong:</strong> Explore products → Elastic SIP Trunking → Trunks (PBX trunking — not ARK’s model).</li>
        <li><strong>Right:</strong> Develop → Voice → Manage → <strong>Credential lists</strong> and <strong>SIP domains</strong>.</li>
    </ul>
    <p>
        Console (US1):
        <a href="https://console.twilio.com/us1/develop/voice/manage/credential-lists" target="_blank" rel="noopener noreferrer">Credential lists</a>
        ·
        <a href="https://console.twilio.com/us1/develop/voice/manage/sip-domains" target="_blank" rel="noopener noreferrer">SIP domains</a>
        ·
        <a href="https://www.twilio.com/docs/voice/sip" target="_blank" rel="noopener noreferrer">Twilio SIP docs</a>
    </p>
    <p>Set the Console <strong>region</strong> to <strong>United States (US1)</strong> unless you deliberately built another region. Credential lists and SIP domains are region-specific.</p>

    <h3>Where webhooks go (shop phone number — not SIP domain)</h3>
    <p>Customer calls hit the <strong>Twilio phone number</strong> configuration, not the SIP domain Call Control form.</p>
    <p><strong>Twilio → Phone Numbers → Active Numbers → your shop number → Configure</strong></p>
    <ul>
        <li><strong>A call comes in</strong> — Webhook · HTTP POST · <code>{{ $voiceWebhook }}</code></li>
        <li><strong>Call status changes</strong> — optional on the number; ARK also sets status callbacks on the ring-group <code>&lt;Dial&gt;</code> → <code>{{ $statusWebhook }}</code></li>
    </ul>
    <p>SMS/MMS uses a separate messaging webhook: <code>{{ $messagingWebhook }}</code></p>
    <p>Operational health and copy-paste URLs: <a href="{{ $communicationsSettings }}">Settings → Communications</a>.</p>

    <h3>SIP domain Call Control — outbound from desk phones</h3>
    <p>On the SIP domain details page, set <strong>Call Control Configuration → A call comes in</strong> to ARK’s SIP outbound webhook. That path handles desk phones dialing out — not customer calls to the shop line.</p>
    <ul>
        <li><strong>A call comes in</strong> — Webhook · HTTP POST · <code>{{ $sipOutboundWebhook }}</code></li>
        <li><strong>Primary handler fails</strong> — leave empty.</li>
        <li><strong>Call status changes</strong> — leave empty; ARK sets status callbacks on outbound <code>&lt;Dial&gt;</code>.</li>
    </ul>
    <p>Clear any legacy <code>lnp.arksms.com</code> URLs. Shop inbound still uses the Twilio phone number Voice URL below — not this SIP domain URL.</p>

    <h3>Step 1 — Credential list (Twilio)</h3>
    <ol>
        <li>Develop → Voice → Manage → <strong>Credential lists</strong> → Create.</li>
        <li>Add one row per desk phone / extension:
            <ul>
                <li><strong>Username</strong> — e.g. <code>101</code> (becomes the extension in the SIP URI).</li>
                <li><strong>Password</strong> — strong password; used on the phone.</li>
            </ul>
        </li>
        <li>Repeat for <code>102</code>, front desk, etc.</li>
    </ol>

    <h3>Step 2 — SIP domain (Twilio) — this is where you get the domain</h3>
    <ol>
        <li>Develop → Voice → Manage → <strong>SIP domains</strong> → Create SIP domain.</li>
        <li><strong>Friendly name</strong> — e.g. <code>Demo Auto Repair shop phones</code>.</li>
        <li><strong>SIP URI</strong> — pick a globally unique slug, e.g. <code>demo-auto</code>.</li>
        <li>After save, Twilio shows the full hostname — copy it exactly, e.g. <code>example.sip.us1.twilio.com</code> (region suffix may differ).</li>
        <li><strong>Voice authentication</strong> — attach your credential list.</li>
        <li><strong>SIP registration</strong> → Edit → <strong>Enable</strong> → attach the same credential list.</li>
        <li>Leave SIP domain <strong>Call Control → A call comes in</strong> pointed at ARK (see above).</li>
    </ol>

    <h3>Step 3 — Register the desk phone</h3>
    <p>Typical IP phone or softphone (Zoiper, Linphone, etc.):</p>
    <ul>
        <li><strong>Username / Auth ID</strong> — <code>101</code> (matches credential list).</li>
        <li><strong>Password</strong> — from credential list.</li>
        <li><strong>Domain / Registrar / SIP server</strong> — <code>example.sip.us1.twilio.com</code> (your full domain from Twilio).</li>
        <li><strong>Transport</strong> — UDP (or TCP/TLS if supported).</li>
    </ul>
    <p>Some clients want the full login: <code>101@example.sip.us1.twilio.com</code>.</p>
    <p>Phone must show <strong>Registered</strong>. Confirm in Twilio on the SIP domain → <strong>Registered endpoints</strong> tab.</p>

    <h3>Step 4 — Add the endpoint in ARK</h3>
    <p><a href="{{ $communicationsSettings }}">Settings → Communications → Telephony → Ring group</a> → Add endpoint → Save telephony settings.</p>
    <p>Example <strong>Desk1</strong> row:</p>
    <ul>
        <li><strong>Name</strong> — <code>Desk1</code> or <code>Front Desk SIP</code></li>
        <li><strong>Type</strong> — SIP</li>
        <li><strong>Destination</strong> — <code>sip:101@example.sip.us1.twilio.com</code> (<code>101@…</code> also works; ARK adds <code>sip:</code>)</li>
        <li><strong>Owner</strong> — staff member for automatic floor ownership when this phone answers (recommended)</li>
        <li><strong>Enabled</strong> — checked</li>
    </ul>
    <p><strong>How to build the destination:</strong> <code>sip:</code> + <strong>credential username</strong> + <code>@</code> + <strong>full SIP domain hostname from Twilio</strong>.</p>
    <p>Example Demo Auto Repair ring group:</p>
    <ol>
        <li>Primary cell — Cell — <code>+17195550199</code></li>
        <li>Front Desk SIP — SIP — <code>sip:101@example.sip.us1.twilio.com</code></li>
        <li>Advisor cell — Cell — <code>+1…</code></li>
    </ol>
    <p>All enabled endpoints ring simultaneously. Cell-only groups work without SIP — add Cell endpoints if desk phones are not ready.</p>

    <h3>Step 5 — Verify</h3>
    <ol>
        <li><a href="{{ $communicationsSettings }}">Settings → Communications</a> — Connection and Voice webhook healthy.</li>
        <li>Twilio SIP domain → Registered endpoints shows the extension.</li>
        <li>Settings → <strong>Test incoming call</strong> — desk phone rings with other enabled endpoints.</li>
        <li>Answer on SIP phone — Calls Waiting should assign <strong>Owner</strong> if configured.</li>
        <li>Dial a customer number from the desk phone — outbound should connect with shop caller ID.</li>
        <li>If dial fails — Twilio Monitor → Logs; URI username must match credential list username.</li>
    </ol>

    <h3>Common failures</h3>
    <ul>
        <li><strong>Can’t find SIP in Twilio</strong> — wrong product (Elastic SIP Trunking) or wrong region. Use links above under Develop → Voice → Manage.</li>
        <li><strong>Phone never rings</strong> — not Registered in Twilio; wrong domain hostname; endpoint disabled in ARK; empty ring group.</li>
        <li><strong>Wrong number rings</strong> — old legacy forward env vars should be removed; ring group is authoritative in Settings.</li>
        <li><strong>Outbound fails</strong> — SIP domain Call Control must point at ARK SIP outbound URL; shop Twilio number must be saved in Settings for caller ID; endpoint must be in ring group.</li>
    </ul>

    <h3>Deployment secrets (not in Settings)</h3>
    <p><code>TWILIO_ACCOUNT_SID</code>, <code>TWILIO_AUTH_TOKEN</code> (optional <code>.env</code> fallback), and Reverb keys for screen pop. Prefer <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Settings → Communications</a> for Twilio credentials.</p>
</div>
