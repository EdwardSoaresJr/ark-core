<div class="ops-learn-prose">
    <h3>One composer, two entry labels</h3>
    <p>ARK texting is not a separate inbox — outbound and inbound live on the relationship as <code>ConversationMessage</code> rows. You compose from Customer Hub <strong>Comms</strong> or the RO communication rail on estimate review.</p>
    <p><strong>Reply</strong> when history exists. <strong>Text Customer</strong> when you are initiating the first shop text to this customer. Same API, same thread authority — label switching only reflects whether you are continuing or starting.</p>
    <p>On Customer Hub, the composer is always open on the Comms tab — no extra click to start typing. Customers text photos and videos daily; attach from the composer or receive MMS inline — media stays on the message, not a parallel MMS product.</p>

    <x-operations.learn.figure
        role="advisor"
        article="texting-customers"
        file="quick-reply-rail.png"
        alt="Quick Reply composer on Customer Hub Comms and RO communication rail"
        caption="Stay on the rail — estimate and payment actions launch from here, not a texting app."
    />

    <h3>Inbound SMS interrupt</h3>
    <p>Fresh inbound texts trigger a centered interrupt pop — snippet, customer match, and primary <strong>Reply</strong>. Same accountability rhythm as incoming calls: handle it or mark read before ARK lets you wander (when comms gate is on).</p>
    <p><strong>Reply</strong> marks the thread read, closes the pop, and focuses the composer — on hub Comms even if you were already viewing that customer. From other pages, Reply navigates to hub or RO compose with the textarea ready.</p>
    <p><strong>Mark Read</strong> clears the interrupt without composing — use when another advisor already responded aloud. Do not mark read to silence a customer who still needs a text back.</p>
    <p>Enter on the pop activates Reply when visible. See also <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a> for call pop behavior.</p>

    <h3>Remote sell from the rail</h3>
    <p><strong>Copy portal link</strong> — creates or reuses a token and copies the URL to clipboard. Use when you need the link in Messenger, a personal email draft, or to read to the customer on a call.</p>
    <p><strong>Email estimate</strong> — sends authoritative PDF + portal review button in one message. Best default for remote sell after check-in.</p>
    <p><strong>Send Estimate</strong> (Quick Reply) — texts the portal link only. Moves RO to <strong>Awaiting approval</strong> and logs <strong>Estimate sent</strong> on the RO when still in estimate posture.</p>
    <p><strong>Send Payment Link</strong> — issued invoice balance only; not for draft estimates. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'portal-payment-links']) }}">Portal payment links</a>.</p>
    <p>Full remote sell flow: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>

    <h3>Floor discipline</h3>
    <p>Read state is per advisor; unread on your screen does not mean nobody responded. Hand off with an internal note when shift changes mid-thread.</p>
    <p>Do not duplicate threads by texting from personal phones — portal links, approval events, and estimate viewed signals will not attach to the customer file.</p>
    <p>Calls Waiting <strong>Text Customer</strong> deep-links into hub compose when you need to reach someone who just called.</p>

    <x-operations.learn.video
        role="advisor"
        article="texting-customers"
        file="walkthrough.mp4"
        video-key="main"
        title="Quick Reply — interrupt reply, MMS, estimate link, payment link"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Customer context: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-hub']) }}">Customer Service Hub</a>.</p>
    <p>Morning triage: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue</a>.</p>
    <p>Selling after send: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>
</div>
