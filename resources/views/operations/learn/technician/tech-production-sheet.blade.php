<div class="ops-learn-prose">
    <h3>Production work order is your authority</h3>
    <p>The Technician Work Order PDF is the bay’s issued production record — approved concerns, labor checklist, parts checklist, and work notes. Print from the RO or open PDF from the print menu; do not work from memory when lines exist.</p>
    <p><strong>Approved Flag Hours</strong> are the hours assigned to approved work on that sheet. They are the shop’s production record for the assignment — not a signed acceptance, and not payroll.</p>
    <p>Reprint when the advisor changes authorization — an old sheet with new verbal instructions causes comebacks and warranty fights.</p>
    <p>Check In sheet is context; Technician Work Order is the production assignment — know which you are holding.</p>

    <x-operations.learn.figure
        role="technician"
        article="tech-production-sheet"
        file="tech-production-pdf.png"
        alt="Technician Work Order PDF with labor and parts checklists"
        caption="Authorized concerns only — deferred and declined items should not appear as assigned work."
    />

    <h3>Using the sheet on the floor</h3>
    <p>Scan concern titles first — they should match the repair you are performing. Mismatch means advisor scope structure problem; clarify before wrenching.</p>
    <p>Labor and Parts are separate checklists. Parts lines are what the advisor ordered — verify physically before install; supersession happens in the advisor layer via PartsTech re-import.</p>
    <p>Work Notes carry advisor notes and internal production notes — not customer estimate copy.</p>

    <h3>Assignment and workboard</h3>
    <p>Workboard in-progress lane means cars the shop expects on lifts — your status updates keep advisor promises honest.</p>
    <p>Advisor printing guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ro-printing']) }}">RO printing</a>.</p>
    <p>Lifecycle when done: advisor moves to ready/pickup — you flag complete in shop rhythm.</p>

    <x-operations.learn.video
        role="technician"
        article="tech-production-sheet"
        file="walkthrough.mp4"
        video-key="main"
        title="Read production work order and confirm authorized work"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Estimate structure: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'reading-estimates']) }}">Reading estimates</a>.</p>
    <p>Collaboration: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'worksheet-collaboration']) }}">Worksheet collaboration</a>.</p>
</div>
