<div class="ops-learn-prose">
    <h3>ARK Mobile is a projection — not a phone app</h3>
    <p><strong>ARK Mobile</strong> is the phone-first staff app for advisors and technicians on the lot. It reads the same authority as desktop ARK — repair orders, conversations, intake, and Attention — through <code>/api/mobile/*</code> only.</p>
    <p>Mobile is <strong>not</strong> Twilio, Asterisk, or a SIP client. Outbound texts and push notifications are sent by ARK on the server; the app never talks to telephony providers directly.</p>
    <p>Sign in with your normal staff account (same email as <a href="{{ route('operations.index') }}">app.demo-auto.test</a>). Technicians see assigned work; advisors see shop comms and intake when permitted.</p>

    <h3>Attention tab (advisors)</h3>
    <p>The <strong>Attention</strong> tab is the mobile slice of morning triage — not a full desktop Work replacement.</p>
    <ul>
        <li><strong>Customer Decision</strong> — dollars waiting on approval, unsent estimates, stalled approved work (same pressure as desktop Work).</li>
        <li><strong>Since Last Shift</strong> — unread relationship messages and comms that arrived while you were away.</li>
        <li><strong>Calls Waiting</strong> — active call queue rows when calls need coverage.</li>
    </ul>
    <p>Tap a row to open the related repair order or conversation thread. Pull down to refresh. Desktop <a href="{{ route('operations.index') }}">Work</a> remains the full recovery surface; mobile Attention is for lot-side catch-up.</p>
    <p>Push notifications (when enabled) alert you to inbound customer texts — same interrupt truth as desktop pop, delivered through ARK FCM, not a separate SMS inbox app.</p>

    <h3>Other advisor mobile flows</h3>
    <p><strong>Check-in</strong> — VIN scan or manual entry, customer match, concern, OBD codes, assign technician, open RO from the lane. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-check-in']) }}">Mobile check-in and OBD</a>.</p>
    <p><strong>Comms</strong> — thread list and reply on Customer Hub–linked conversations; Quick Reply path matches desktop rail.</p>
    <p>Related desktop guides: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue triage</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']) }}">Service counter check-in</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>

    <h3>Admin setup</h3>
    <p>Install APK on shop devices: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-android-deploy']) }}">ARK Mobile Android deploy</a>.</p>
    <p>Firebase push credentials (deferred): <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-push-setup']) }}">ARK Mobile push setup</a>.</p>
</div>
