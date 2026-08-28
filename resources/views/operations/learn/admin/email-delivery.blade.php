<div class="ops-learn-prose">
    <h3>Estimate email is delivery, not authority</h3>
    <p>Email estimate sends the authoritative PDF snapshot from the RO <strong>and</strong> a portal review link in the same message — customer gets paper trail plus one-click approve/defer. Same pipeline as print/download; different transport.</p>
    <p>Configure Postmark (or shop mail driver) in Settings → Email via <code>operations.settings.shop.email.update</code>.</p>
    <p>Failed email does not change estimate totals — fix delivery, do not rebuild lines.</p>

    <x-operations.learn.figure
        role="admin"
        article="email-delivery"
        file="email-settings.png"
        alt="Shop email settings with from address and driver configuration"
        caption="From address should match domain customers recognize — reduces spam folder losses."
    />

    <h3>Deliverability basics</h3>
    <p>Authenticate sending domain (SPF/DKIM) with your mail provider before high volume — test to personal Gmail and shop domain inbox.</p>
    <p>From name and reply-to should route to a monitored shop inbox — customers reply to estimate emails with questions.</p>
    <p>Bounce handling: when provider suppresses an address, update customer email in hub before resend.</p>

    <h3>Advisor usage</h3>
    <p>Email estimate from the RO communication rail on estimate review — advisor triggers, server sends, conversation + comm event log the attempt.</p>
    <p>Send moves RO to <strong>Awaiting approval</strong> when still in estimate posture — aligns workboard with Tekmetric-style pending customer decision.</p>
    <p>SMS link-only send remains valid for text-first customers; email is preferred when you want PDF + portal together.</p>
    <p>Advisor guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>

    <h3>Document settings</h3>
    <p>Settings → Document settings: authorization language appears on portal when signature is required; optional <strong>Require customer signature on portal authorization</strong> for digital sign-off (Tekmetric-style).</p>

    <h3>Related guides</h3>
    <p>Print path: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ro-printing']) }}">RO printing</a>.</p>
    <p>Texting: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
    <p>Comms health: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'comms-health-check']) }}">Comms health check</a>.</p>
</div>
