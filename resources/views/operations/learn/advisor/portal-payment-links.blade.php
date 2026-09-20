<div class="ops-learn-prose">
    <h3>Pay without the counter</h3>
    <p>Portal payment links let customers pay issued invoice balance from their phone — initiated from Quick Reply on Customer Hub or RO communication rail. ARK validates invoice state and balance due before send.</p>
    <p>Every send records a <code>ConversationMessage</code> — you see the link in thread history alongside estimate links and advisor notes.</p>
    <p>Disabled portal pay in Settings blocks send with a clear error — fix configuration, do not paste manual payment URLs outside ARK.</p>

    <x-operations.learn.figure
        role="advisor"
        article="portal-payment-links"
        file="send-payment-link.png"
        alt="Send Payment Link action on Quick Reply composer"
        caption="Send only after invoice issued — draft estimate balance is not portal-payable."
    />

    <h3>Not for estimate deposits</h3>
    <p>Portal payment links show <strong>issued invoice balance</strong> — not draft estimates. Online card capture is not part of Core; customers pay at the shop and staff record the payment on the RO. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits and invoicing</a>.</p>
    <p>Remote sell estimate links come from <strong>Copy portal link</strong>, <strong>Email estimate</strong>, or <strong>Send Estimate</strong> — not Send Payment Link. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>

    <h3>When to send</h3>
    <p>Customer picked up already but calls to pay — send the balance link if helpful, then record the payment on the RO when money clears.</p>
    <p>Partial payments follow shop policy — portal reflects current balance due; do not promise zero balance until ARK shows paid.</p>
    <p>Combine with pickup coordination — text “car ready” and payment link in one thread, not three separate channels.</p>

    <h3>Failure modes</h3>
    <p>Expired or revoked invoice tokens require re-issue from RO — do not resend ancient links from clipboard history.</p>
    <p>Paid state comes from the RO ledger — if a customer insists they paid but ARK still shows due, confirm the payment was recorded on the repair order before arguing.</p>
    <p>Owner setup: <a #>Payment recording</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="portal-payment-links"
        file="walkthrough.mp4"
        video-key="main"
        title="Send payment link and confirm paid state"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Texting: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
        <p>Invoice: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits and invoicing</a>.</p>
</div>
