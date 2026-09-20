<div class="ops-learn-prose">
    <h3>Remote sell after check-in</h3>
    <p>Most customers at Demo Auto Repair approve from their phone after intake — not at the counter. ARK’s remote sell path is: build a presentable estimate in review mode, send the customer a portal link, they approve or defer each <strong>recommended</strong> scope, shop moves forward automatically when authority is captured.</p>
    <p>The portal is not a second estimate — it is the same grouped scopes, totals, and narrative you see in review mode. PDF, email, SMS, and portal all read from the same authoritative snapshot pipeline.</p>

    <x-operations.learn.figure
        role="advisor"
        article="remote-sell"
        file="communication-rail-remote.png"
        alt="Repair order communication rail with copy link, email estimate, and Quick Reply send estimate"
        caption="Communication rail on estimate review — remote sell lives here, not in a separate texting app."
    />

    <h3>Three ways to reach the customer</h3>
    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Action</th>
                <th>What the customer gets</th>
                <th>When to use</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Copy portal link</strong></td>
                <td>Browser link to approve/defer or review approved work</td>
                <td>Paste into Messenger, email body you write elsewhere, or read aloud on phone</td>
            </tr>
            <tr>
                <td><strong>Email estimate</strong></td>
                <td>PDF attachment + portal button in email body</td>
                <td>Best default for remote sell — formal paper trail + one-click approval</td>
            </tr>
            <tr>
                <td><strong>Send Estimate</strong> (Quick Reply SMS)</td>
                <td>Text with portal link only</td>
                <td>Fast follow-up when customer lives in texts</td>
            </tr>
        </tbody>
    </table>
    <p>Email or SMS send moves the RO from <strong>Estimate</strong> to <strong>Awaiting approval</strong> when the estimate was still in build posture — same as Tekmetric “pending customer decision.”</p>

    <h3>What the customer sees on the portal</h3>
    <p><strong>Recommended</strong> scopes show on the portal with approve / defer choices and a name field (plus optional signature when admin enables it in Settings → Document settings).</p>
    <p><strong>Approved</strong> scopes already sold by phone or counter are <strong>read-only</strong> on the portal — ARK shows “Authorization on file” or “Work approved,” not a second signature request.</p>
    <p><strong>Draft</strong> scopes never appear on portal, PDF, or customer email — internal build only. Customer status on the portal reflects visible scope state, not internal RO slug labels.</p>
    <p>First portal open logs <strong>Estimate viewed</strong> once — follow up when viewed but silent.</p>

    <h3>After the customer acts</h3>
    <p>Portal approval records an immutable <code>ApprovalEvent</code>, updates concern dispositions, recalculates totals, and advances lifecycle toward <strong>Approved</strong> / <strong>Ready for work</strong> when nothing blocks (parts, tech assignment).</p>
    <p>After authorization, collect deposits on the counter financial rail (cash, check, or card taken externally). Online card capture is not part of Core.</p>
    <p>Phone or counter approval: use <strong>Record authorization</strong> on the authorization rail with source Phone, In person, SMS, etc. — same lifecycle advance as portal.</p>

    <h3>Floor discipline</h3>
    <p>Do not text portal links from personal phones — conversation history and approval events stay in ARK only when you send from the rail.</p>
    <p>Verbal “yes do the brakes” still requires disposition + authorization record on the RO — text thread alone is not financial authority.</p>
    <p>Before send: scan review mode — draft scopes hidden, recommended scopes ready, note privacy correct. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'note-privacy']) }}">Customer-visible vs staff notes</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="remote-sell"
        file="walkthrough.mp4"
        video-key="main"
        title="Copy link, email PDF, SMS, and portal approval"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Authorization posture: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>
    <p>Texting: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
    <p>Email delivery: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'email-delivery']) }}">Shop email delivery</a>.</p>
    <p>Deposits after auth: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits and invoicing</a>.</p>
    <p>Invoice pay at pickup: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'portal-payment-links']) }}">Portal payment links</a>.</p>
</div>
