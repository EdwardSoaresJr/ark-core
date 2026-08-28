<div class="ops-learn-prose">
    <h3>One worksheet, two jobs</h3>
    <p>Advisors own customer-facing structure — scopes, repair actions, pricing, authorization. Technicians own bay truth — findings, hours feedback, parts actually installed, completion notes.</p>
    <p>ARK keeps both on the same repair order without duplicate ROs. Fight scope problems in notes early, not after the customer approved the wrong story.</p>
    <p>Review mode output is what you execute — if production sheet does not match the bay, stop and ping advisor before burning time.</p>

    <x-operations.learn.figure
        role="technician"
        article="worksheet-collaboration"
        file="advisor-tech-handoff.png"
        alt="Repair order with advisor scopes and technician findings side by side"
        caption="Findings live under the problem; repair actions live under the sell — never swap the two."
    />

    <h3>Live presence and drift banners</h3>
    <p>When another staff member has the same RO open, a sky banner shows who else is on the worksheet — read-only awareness, not a chat room.</p>
    <p><strong>Version drift</strong> (amber): someone else saved estimate changes while your tab was stale. Refresh before you add lines — server wins on save conflicts.</p>
    <p><strong>Stale notice</strong> (amber): your session lost sync with the server estimate version. Dismiss after refresh if you confirmed you are current.</p>
    <p>Do not fight another tab — refresh, call across the bay, or let the advisor finish pricing before you mark complete.</p>

    <h3>Communication norms</h3>
    <p>Internal notes for urgency — “customer waiting”, “needs lift tonight”, “found secondary leak”. Customer-visible fields for facts only — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'note-privacy']) }}">Customer-visible vs staff notes</a>.</p>
    <p>Parts list completeness before dispatch — advisor orders from estimate lines; you add discovered parts through advisor update, not silent pocket purchases.</p>
    <p>Hours reality: if guide hours are fantasy on this rust bucket, tell advisor before they promise same-day — ELR and schedule both suffer.</p>

    <h3>Status discipline</h3>
    <p>Update RO status when you start, pause, and finish — workboard and advisor texts rely on it. See <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'ro-status']) }}">RO status for technicians</a>.</p>
    <p>Do not mark complete with open findings unreviewed — advisor may still be selling deferred items from your MPI.</p>
    <p>Advisor counterpart: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'estimate-review-mode']) }}">Estimate review mode</a>.</p>

    <x-operations.learn.video
        role="technician"
        article="worksheet-collaboration"
        file="walkthrough.mp4"
        video-key="main"
        title="Advisor–tech handoff on one RO"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Findings: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'writing-findings']) }}">Writing findings</a>.</p>
    <p>Reading sell: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'reading-estimates']) }}">Reading estimates</a>.</p>
</div>
