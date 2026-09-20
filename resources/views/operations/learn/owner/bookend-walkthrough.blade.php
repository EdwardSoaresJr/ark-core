<div class="ops-learn-prose">
    <h3>Day Review closes the day</h3>
    <p><a href="{{ route('operations.owner.day-review') }}">Day Review</a> is the owner’s end-of-day queue review — not a dashboard theater screen. It asks: what is still open, what approvals aged, what is tomorrow’s first move?</p>
    <p>Use it after the bay quiets, before you mentally clock out. Same scan advisors should have done on the workboard — elevated to owner decision height.</p>
    <p>Day Review complements Operational Report pulse — the report is numbers (Sales Posted, Cash Collected, reconciliation); Day Review is operational unfinished business.</p>

    <x-operations.learn.figure
        role="owner"
        article="bookend-walkthrough"
        file="bookend-queue.png"
        alt="Day Review end-of-day queue with aging approvals and open work"
        caption="Oldest items are tomorrow’s landmines if ignored tonight."
    />

    <h3>Walkthrough rhythm</h3>
    <p>Scan aging approvals — each is deferred revenue or angry callback. Advisors should have texted; Day Review catches what they did not.</p>
    <p>Scan in-progress and waiting-parts — promise dates for tomorrow’s phone calls write themselves from honest notes tonight.</p>
    <p>Glance at today’s reconciliation on the Financial tab — if cash and posted do not foot, note one RO to fix tomorrow morning.</p>
    <p>Note one first move for morning opening — who calls which customer, which part to chase, which RO to dispatch first.</p>

    <h3>Owner digest email</h3>
    <p>Active admins can receive a daily digest with Sales Posted, Cash Collected, reconciliation status, queue pressure, and links to Financial tab and Day Review. Schedule and enable in <strong>Settings → Owner Targets &amp; Reporting</strong>. The email is a backup nudge — Day Review in ARK is still the authoritative close.</p>

    <h3>Connect to advisor floor</h3>
    <p>Morning advisor counterpart: <a href="{{ route('operations.index') }}">Home</a>, then the <a href="{{ route('operations.workboard') }}">Workboard</a> for lane detail.</p>
    <p>Advisor lifecycle guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'lifecycle-transitions']) }}">Lifecycle transitions</a>.</p>
    <p>Daily rhythm owner doc: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-rhythm']) }}">Daily rhythm</a>.</p>

    <x-operations.learn.video
        role="owner"
        article="bookend-walkthrough"
        file="walkthrough.mp4"
        video-key="main"
        title="Five-minute Day Review before you leave"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>KPIs: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-kpis']) }}">Daily KPIs</a>, <a href="{{ route('operations.reports.operational') }}">Operational Report</a>, <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'payments-reconciliation']) }}">Payments reconciliation</a>.</p>
    <p>Targets: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'quarterly-target-review']) }}">Quarterly target review</a>.</p>
</div>
