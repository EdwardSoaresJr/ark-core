<div class="ops-learn-prose">
    <h3>Field work without the desktop</h3>
    <p>Technicians use ARK Mobile for <strong>assigned repair orders only</strong> — not shop-wide workboard, customer search, or comms queue. Walk to the vehicle, open your job from <strong>My Work</strong>, and document truth beside the car.</p>
    <p>Sign in with your staff account. Each row shows vehicle identity, primary concern, age, and a <strong>next action</strong> hint (inspect, document findings, complete scope).</p>

    <h3>Primary workflow</h3>
    <ol>
        <li><strong>My Work</strong> — open an assigned RO.</li>
        <li><strong>Concern workspace</strong> — tap the concern; stay in one surface for findings, photos, notes, and production. Full guide: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'ark-mobile-concern-workspace']) }}">Concern workspace on mobile</a>.</li>
        <li><strong>Complete scope</strong> — when authorized work is finished, mark the concern complete from the quick action.</li>
        <li><strong>Next vehicle</strong> — return to My Work; repeat.</li>
    </ol>

    <h3>Core capabilities</h3>
    <ul>
        <li><strong>Findings</strong> — verified findings, recommendations, and DTC summary on the concern; same authority as desktop inspection capture.</li>
        <li><strong>Photos</strong> — capture evidence on the concern; offline queue uploads when signal returns.</li>
        <li><strong>Production notes</strong> — internal note lines for advisor handoff.</li>
        <li><strong>Production status</strong> — scope chips (waiting parts, in progress, complete) on approved work.</li>
        <li><strong>Comms</strong> — read and internal-note on conversations linked to assigned ROs.</li>
    </ul>

    <h3>What mobile is not</h3>
    <p>Not a replacement for the full RO workspace, estimate builder, or inspection checklist UI on desktop. Not a telephony or SIP app — you do not place shop calls from Flutter.</p>

    <h3>Quality habits</h3>
    <p>Complete findings with photos before the advisor presents — same discipline as <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'multi-point-inspection']) }}">Multi-point inspection</a>.</p>
    <p>Use scope production status honestly so the workboard and advisor see bay truth without a walk to the office.</p>
    <p>Related: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'writing-findings']) }}">Documenting findings</a>, <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'tech-production-sheet']) }}">Tech sheet and assignment</a>, <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-android-deploy']) }}">Android install</a>.</p>
</div>
