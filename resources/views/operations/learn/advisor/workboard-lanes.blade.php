<div class="ops-learn-prose">
    <h3>What the workboard is for</h3>
    <p>The <a href="{{ route('operations.workboard') }}">Workboard</a> is the shop’s live pressure map — not a report, not a to-do list you manually maintain. Each lane groups repair orders by workflow posture so you can scan the floor in seconds.</p>
    <p><a href="{{ route('operations.index') }}">Work</a> shows customer decisions, follow-ups, and shop pressure counts; open the workboard when you need the full lane board. Return after every counter interruption for aging approvals, parts blockers, and unclaimed dispatch.</p>
    <p>If a job feels lost, search from <a href="{{ route('operations.repair-orders.index') }}">Repair Orders</a> — the workboard only shows active posture bands, not every historical RO.</p>

    <x-operations.learn.figure
        role="advisor"
        article="workboard-lanes"
        file="workboard-lanes-overview.png"
        alt="Workboard lanes with intake, approval, and in-progress columns"
        caption="Lanes read left-to-right as shop rhythm — estimate pressure on the left, execution on the right."
    />

    <h3>Lane meanings</h3>
    <p><strong>Estimates</strong> — draft scopes and estimates in build. Recognition, concern capture, and a sellable story — not dispatch.</p>
    <p><strong>Waiting approval</strong> — estimate is presentable; customer has not authorized work yet. Follow up by text, call, or counter conversation. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>
    <p><strong>Waiting parts</strong> — approved work is blocked on procurement. Parts status must be honest or techs and customers lose trust. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'parts-procurement']) }}">Parts status and waiting-parts</a>.</p>
    <p><strong>Shop floor</strong> — authorized, in-progress, and QC work in the building. Assignment and tech sheet handoff happen here. Technicians read <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'tech-production-sheet']) }}">Tech sheet and assignment</a>.</p>
    <p><strong>Ready pickup</strong> — complete, invoice, and release pressure. Collect balance before the vehicle leaves when required.</p>

    <h3>How to triage without chaos</h3>
    <p>Scan one lane at a time. Within a lane, oldest-first is usually the right rhythm — the customer waiting longest is often the one who will call angry if you skip them.</p>
    <p>Do not treat lane moves as busywork. Each transition should reflect real shop truth: you would say the same thing out loud on the floor. Wrong lane = wrong promise to the customer.</p>
    <p>When two advisors share the floor, use workspace tab signals and the comms surfaces so you are not both working the same approval thread. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workspace-tabs']) }}">Workspace tabs</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="workboard-lanes"
        file="walkthrough.mp4"
        video-key="main"
        title="Workboard scan — morning triage in under two minutes"
        poster-file="poster.jpg"
    />

    <h3>Morning and end-of-shift habits</h3>
    <p>Before you touch a wrench or open intake, start at <a href="{{ route('operations.index') }}">Work</a> for overnight customer decisions, follow-ups, and shop counts, then open the workboard for lane detail.</p>
    <p>At shift end, glance at approvals still aging and parts still on order — those are tomorrow’s first moves. Owners use <a href="{{ route('operations.owner.day-review') }}">Day Review</a> for the same scan at a higher level.</p>
    <p>New advisors: read <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']) }}">Advisor basics</a> first, then keep this guide open until lane names match what you say at the counter.</p>
</div>
