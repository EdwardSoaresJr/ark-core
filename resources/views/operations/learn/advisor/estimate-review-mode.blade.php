<div class="ops-learn-prose">
    <h3>Review is the default</h3>
    <p>Estimate <strong>review mode</strong> is the primary posture on the RO — scan totals, scopes, findings, and customer-facing narrative before you sell or dispatch. Open it from the RO workspace header when you are ready to present, not while you are still adding lines.</p>
    <p>Review keeps edit chrome collapsed so the customer story reads cleanly at the counter. Switch to worksheet edit only when you are actively building or restructuring lines.</p>
    <p>Route: <code>operations.repair-orders.show</code> and <code>operations.repair-orders.show</code> land review posture; edit uses <code>operations.repair-orders.show</code>.</p>

    <x-operations.learn.figure
        role="advisor"
        article="estimate-review-mode"
        file="estimate-review-layout.png"
        alt="Estimate review mode with scope groups and totals rail"
        caption="Totals rail stays authoritative — never mentally recalculate tax in your head."
    />

    <h3>What to scan before present</h3>
    <p>Scope headlines read as customer problems — not internal shorthand. Recommendation sentences tell the story before line items.</p>
    <p>Repair actions group labor and parts per named fix. Orphan parts floating outside actions confuse techs and customers on PDFs.</p>
    <p>Financial literacy: parts margin and labor hours are shop levers — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'financial-literacy-basics']) }}">Financial literacy basics</a> before discounting.</p>

    <h3>Communication rail — remote sell</h3>
    <p>The communication rail on estimate review is the remote sell surface: <strong>Copy portal link</strong>, <strong>Email estimate</strong> (PDF + portal button), and <strong>Send Estimate</strong> (SMS with portal link).</p>
    <p>Present at the counter from review mode when possible — same grouped scopes the portal and PDF use. Draft scopes stay internal; customer-facing output hides them.</p>
    <p>Email or SMS send moves RO to <strong>Awaiting approval</strong> when still in estimate posture. Full flow: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>
    <p>After verbal yes, record authorization on the authorization rail — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="estimate-review-mode"
        file="walkthrough.mp4"
        video-key="main"
        title="Review mode scan before customer present"
        poster-file="poster.jpg"
    />

    <h3>Collaboration with tech</h3>
    <p>When another staff member has the same RO open, sky banners show who else is on the worksheet. Amber <strong>version drift</strong> means someone saved estimate changes while your tab was stale — refresh before you add lines; server wins on save conflicts.</p>
    <p>Technicians consume review output on production sheets — messy scopes become messy bay work. See <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'worksheet-collaboration']) }}">Worksheet collaboration</a>.</p>
    <p>Structure rules: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']) }}">Repair actions</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'scopes-and-intent']) }}">Scopes and intent</a>.</p>
</div>
