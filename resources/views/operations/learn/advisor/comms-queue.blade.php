<div class="ops-learn-prose">
    <h3>Recovery, not interrupt</h3>
    <p><a href="{{ route('operations.index') }}">Work</a> answers: <strong>what requires action today?</strong> Customer decisions and follow-ups first, communications recovery second, shop pressure last — not a message archive and not the live interrupt surface.</p>
    <p>Live interrupt handles right-now events — ringing calls and fresh inbound texts pop centered on screen with <strong>Reply</strong>, <strong>Mark Read</strong>, or call actions. Topbar <strong>Comms</strong> and the sticky pressure bar count what still needs a human; Work is where you clear the backlog.</p>
    <p>When <strong>Advisor comms accountability</strong> is enabled (Settings → Communications), ARK redirects you to Work until unread interrupts are handled — Reply, Mark Read, or Mark Handled counts as cleared. Reply destinations (hub compose or RO text rail) stay reachable while you respond.</p>
    <p>Open Work first thing, then the <a href="{{ route('operations.workboard') }}">Workboard</a> when you need lane detail. Same customer may appear in both; Work tells you <em>respond or convert</em>, workboard tells you <em>move the job</em>.</p>

    <x-operations.learn.figure
        role="advisor"
        article="comms-queue"
        file="comms-queue-sections.png"
        alt="Work surface with Since Last Shift and Needs Attention sections"
        caption="Since Last Shift is oldest-first — the customer waiting longest is often the angriest if skipped."
    />

    <h3>Section rhythm</h3>
    <p><strong>Customer Decisions</strong> is system-generated — dollars waiting on approval, unsent estimates, or stalled payment. Sort by amount at risk; act before the job ages off the counter’s mental list.</p>
    <p><strong>Since Last Shift</strong> uses your prior presence timestamp — what arrived while you were away. Sort oldest-first on purpose; advisors adopt this scan naturally when trained once.</p>
    <p><strong>Needs Attention</strong> projects items that require human action: unread relationship messages, estimate views needing follow-up, handoffs, voicemails when enabled. Not every sent message belongs here — read messages drop out.</p>
    <p>Section counts in headers (<em>Since Last Shift (8)</em>) are operational signals, not KPIs. Clear the section by acting, not by marking read without doing the work.</p>

    <h3>Row actions</h3>
    <p>Each row links customer context, snippet, age, and channel hint. You do not care which channel produced it on the floor — you care who needs a response and whether an RO is attached.</p>
    <p><strong>Reply</strong> on a row jumps straight to compose — Customer Hub Comms or RO text rail with the composer open. Same path as interrupt <strong>Reply</strong>; use it for morning catch-up when you missed the live pop.</p>
    <p>Jump to Customer Hub or RO from the row when you need full history. Send estimate links and payment links from Quick Reply on those surfaces — not from a parallel SMS tool.</p>
    <p>Unknown callers stay in queue until resolved to a customer or dismissed with honest handled state on calls. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="comms-queue"
        file="walkthrough.mp4"
        video-key="main"
        title="Morning queue scan in under three minutes"
        poster-file="poster.jpg"
    />

    <h3>Habits that stick</h3>
    <p>Do not use the queue as your only texting UI — it is triage. Ongoing threads belong on hub Comms or RO communication rail during the day.</p>
    <p>When the rose pressure bar shows customers waiting, open Work before diving into ROs — the gate exists so texts do not age while you build estimates.</p>
    <p>Add follow-ups on Work when a customer asks you to call back Thursday — the system surfaces pressure, but you own the reminder.</p>
    <p>End of shift: glance at what will land in tomorrow’s Since Last Shift — open approvals and unanswered texts you deliberately deferred.</p>
    <p><strong>ARK Mobile:</strong> advisors on the lot can scan the same pressure in the app <strong>Attention</strong> tab — Customer Decision, Since Last Shift, Calls Waiting — without opening desktop Work. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-attention']) }}">ARK Mobile Attention</a>.</p>
    <p>Related: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workboard-lanes']) }}">Workboard lanes</a>.</p>
</div>
