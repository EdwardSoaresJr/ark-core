<div class="ops-learn-prose">
    <h3>Comms must work before peak hour</h3>
    <p>Communications health is admin hygiene — SMS webhooks, telephony ingress, ring groups, and advisor endpoints must be verified before Monday morning, not during a five-call pileup.</p>
    <p>Settings → Communications holds Twilio URLs, ring group membership, and test tools. Owner overview: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Communications setup</a>.</p>
    <p>ARK separates interrupt (live pop + topbar Comms) from recovery (<a href="{{ route('operations.communications.queue') }}">Comms Queue</a> and <a href="{{ route('operations.index') }}">Work</a>) — health check both paths.</p>
    <p><strong>Advisor comms accountability</strong> (Settings → Communications → Call flow) can block other ARK pages until Work queue items are cleared — verify gate behavior after deploy, not during peak hour.</p>

    <x-operations.learn.figure
        role="admin"
        article="comms-health-check"
        file="comms-settings-health.png"
        alt="Communications settings with webhook URLs and test call button"
        caption="Run test incoming call after any SIP or cell roster change."
    />

    <h3>Weekly checklist</h3>
    <p>Inbound call test from Settings — pop appears centered, queue updates, claim/dismiss works. SIP detail: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']) }}">Telephony and SIP desk phones</a>.</p>
    <p><strong>Desk phones:</strong> Twilio SIP domain → <strong>Registered endpoints</strong> shows the extension; Settings → <strong>Test incoming call</strong> rings the handset with other enabled endpoints.</p>
    <p>Send test SMS inbound/outbound — message lands as <code>ConversationMessage</code>, interrupt pop fires for unread inbound, and Customer Hub Comms timeline updates (Reverb live or polling fallback).</p>
    <p>After deploy: restart Reverb if screen pop or hub realtime is stale — broadcast auth and <code>reverb:restart</code> on release are part of comms health.</p>
    <p>Escalation SMS (optional): when enabled, unhandled calls/texts text active advisors after the delay — confirm staff profiles have mobile numbers.</p>
    <p>If Messenger is enabled: message the Facebook Page from a personal account — webhook should read Healthy in <a href="{{ route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => 'messenger']) }}">Settings → Messenger</a>. Setup: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'messenger-setup']) }}">Facebook Messenger setup</a>.</p>
    <p>Verify each advisor who answers has endpoint in ring group — silent endpoints mean calls roll to voicemail while someone stares at a dead pop.</p>
    <p>ARK Mobile push (optional): confirm <strong>Settings → Communications → Mobile</strong> when using FCM — <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-push-setup']) }}">ARK Mobile push setup</a>.</p>

    <h3>When traffic fails</h3>
    <p>Twilio debugger first — webhook 4xx usually means deploy URL or signature mismatch after domain change.</p>
    <p>Do not create parallel SMS systems — fix Conversation ingress. Advisor texting guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
    <p>Email delivery is separate channel — see <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'email-delivery']) }}">Email delivery</a> for estimate PDF mail path.</p>

    <x-operations.learn.video
        role="admin"
        article="comms-health-check"
        file="walkthrough.mp4"
        video-key="main"
        title="Weekly comms health check — call and SMS"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Advisor floor: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue</a>.</p>
</div>
