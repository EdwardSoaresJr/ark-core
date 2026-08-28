<div class="ops-learn-prose">
    <h3>Terminal at the counter</h3>
    <p>Square Terminal is card-present capture tied to the RO — customer taps chip or swipe on the device while you stay in ARK. Initiate payment from the RO financial actions; poll until complete or cancel honestly.</p>
    <p>Do not run Terminal for a different RO than the one on screen — payment attempts attach to authoritative invoice balance on that order.</p>
    <p>Device pairing happens in Settings under Payments — admins generate device codes once; advisors only initiate and poll daily.</p>

    <x-operations.learn.figure
        role="advisor"
        article="square-terminal-floor"
        file="square-terminal-initiate.png"
        alt="Square Terminal payment initiation on repair order"
        caption="Wait for ARK confirmation — do not hand keys until payment status shows captured or deferred policy allows."
    />

    <h3>Keyed and fallback</h3>
    <p>When Terminal is offline, keyed entry may be available per shop policy — still through RO payment flow, not standalone Square app without RO linkage.</p>
    <p>Cancel stuck attempts before starting a second — duplicate attempts confuse balance due and webhook reconciliation.</p>
    <p>Deposits vs final balance: confirm amount aloud before customer taps — partial payments should match what you explained.</p>

    <h3>After capture</h3>
    <p>Payment update on RO reflects applied amount; invoice balance due shrinks server-side. Text receipt only if shop policy requires — conversation rail supports follow-up messages.</p>
    <p>Portal pay remains for customers not at counter — do not Terminal-charge a card mailed over the phone unless policy explicitly allows keyed flow.</p>
    <p>Admin: <a href="{{ route('operations.settings.shop.edit') }}">Settings → Payments</a> and owner guide <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'square-payments-setup']) }}">Square payments setup</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="square-terminal-floor"
        file="walkthrough.mp4"
        video-key="main"
        title="Initiate Terminal payment and confirm in ARK"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Remote pay: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'portal-payment-links']) }}">Portal payment links</a>.</p>
    <p>Invoice posture: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits and invoicing</a>.</p>
</div>
