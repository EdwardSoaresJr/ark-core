<div class="ops-learn-prose">
    <h3>MPI is shop revenue infrastructure</h3>
    <p>Multi-point inspection is how Demo Auto Repair turns every visit into honest vehicle health documentation — not a checkbox for free oil changes. Complete the template every time policy requires; partial inspections hide deferred work from the customer and the owner.</p>
    <p>ARK inspection items map to findings advisors convert to recommendations. Skip items only when physically impossible on that vehicle — not because you are rushed.</p>
    <p>Photos on red/yellow items are standard — customers believe pictures; advisors sell pictures.</p>

    <x-operations.learn.figure
        role="technician"
        article="multi-point-inspection"
        file="mpi-checklist.png"
        alt="Multi-point inspection checklist with pass fail and measure states"
        caption="Green is not free — it is trust. Yellow and red are follow-up dollars when advisors present."
    />

    <h3>Execution rhythm</h3>
    <p>Inspect in a consistent order — tires, brakes, fluids, leaks, steering, battery — so you do not walk the bay twice.</p>
    <p>Mark N/A with reason when component absent — advisor needs to know you looked, not that you forgot.</p>
    <p>Finish findings before you flag the advisor for present — half-done MPI forces counter rework under customer stare.</p>

    <h3>Handoff to advisor</h3>
    <p>Notify via shop rhythm (verbal, production sheet note, RO status) when MPI complete on waiting vehicles.</p>
    <p>Advisor path: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'inspection-to-aro']) }}">Inspection to ARO</a>.</p>
    <p>Finding quality: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'writing-findings']) }}">Writing findings</a>.</p>

    <x-operations.learn.video
        role="technician"
        article="multi-point-inspection"
        file="walkthrough.mp4"
        video-key="main"
        title="Complete MPI and flag advisor-ready"
        poster-file="poster.jpg"
    />

    <h3>Production context</h3>
    <p>Authorized work still comes from customer approval — MPI does not authorize lines by itself. See <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'reading-estimates']) }}">Reading estimates</a>.</p>
    <p>Tech sheet: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'tech-production-sheet']) }}">Tech production sheet</a>.</p>
</div>
