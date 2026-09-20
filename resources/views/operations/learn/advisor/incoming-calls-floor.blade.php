<div class="ops-learn-prose">
    <h3>Answer inside ARK</h3>
    <p>When the shop line rings, ARK should be the first screen you look at — not the handset display alone. Incoming call pop ties caller ID to customer lookup and queues the call on <a href="{{ route('operations.telephony.call-queue') }}">Calls Waiting</a>.</p>
    <p>Call and SMS interrupts appear <strong>centered on screen</strong> — not tucked in a corner. Primary action (<strong>Callback</strong>, <strong>Reply</strong>, or intake link) receives keyboard focus; press <kbd>Enter</kbd> to activate. Click empty dialog space to steer focus back to the primary action.</p>
    <p>Pop shows who ARK thinks is calling or texting, open RO context when available, and actions to claim, dismiss, reply, or open lookup. Claiming is floor ownership — not the same as “call handled” or “issue resolved.”</p>
    <p>Two advisors on the floor: first claim wins for ownership; the other sees the call as owned and avoids duplicate hellos.</p>

    <x-operations.learn.figure
        role="advisor"
        article="incoming-calls-floor"
        file="incoming-call-pop.png"
        alt="Incoming call popup with customer match and claim button"
        caption="Match confidence varies — always confirm name before discussing vehicle history aloud."
    />

    <h3>Match, miss, and unknown</h3>
    <p>Strong match → jump to Customer Hub or start intake with customer pre-selected. Weak match → verify aloud before you open their file in front of a stranger.</p>
    <p>No match → <a href="{{ route('operations.intake.create') }}">start intake</a> with the caller’s phone prefilled or run <a href="{{ route('operations.caller-lookup') }}">caller lookup</a> without inventing a duplicate customer record.</p>
    <p>Mark <strong>Handled</strong> on Calls Waiting when coverage is done — customer got a human answer or a honest callback promise — even if no RO exists yet.</p>

    <h3>Handoff to work</h3>
    <p>Convert phone inquiries to intake when the customer wants to schedule or drop off. Capture concern in their words before you hang up — scope naming discipline starts on the call.</p>
    <p>Send follow-up texts from hub Comms or RO Quick Reply after the call; do not text from personal phones for shop business.</p>
    <p>Inbound SMS uses the same interrupt surface — <strong>Reply</strong> marks read and opens the composer (even when you are already on that customer’s Comms tab). See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
    <p>Voicemail and recording surfaces project into the comms queue when enabled — same triage rhythm as texts.</p>
    <p><strong>Mobile:</strong> caller context and Attention project to the ARK Staff app through the same API authority — not a separate phone app. In-app calling uses <strong>ARK Phone</strong> when your shop has enabled it on the device.</p>

    <x-operations.learn.video
        role="advisor"
        article="incoming-calls-floor"
        file="walkthrough.mp4"
        video-key="main"
        title="Ring to claim to intake handoff"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Morning missed items: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue triage</a>.</p>
    <p>Admin SIP and ring groups: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']) }}">Telephony and SIP desk phones</a>.</p>
    <p>Text after the call: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
</div>
