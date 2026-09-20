<div class="ops-learn-prose">
    <h3>Findings feed the advisor</h3>
    <p>Your findings are the raw material for scope recommendations and ARO. Write what you measured, saw, and tested — not the sales pitch. Advisors translate findings into customer language on the estimate.</p>
    <p>Each finding should stand alone: location, severity, measurement when applicable, photo when helpful. “Bad belt” is useless; “Serpentine belt frayed at alternator pulley, 3/8 inch section missing” is billable context.</p>
    <p>Assume the customer may eventually read promoted text — no shop slang, no customer blame, no jokes in finding fields.</p>

    <x-operations.learn.figure
        role="technician"
        article="writing-findings"
        file="finding-entry.png"
        alt="Inspection finding entry with measurement and photo attachment"
        caption="Photo plus one factual sentence beats three paragraphs of opinion."
    />

    <h3>Structure advisors expect</h3>
    <p>Group findings under the scope problem they belong to — brake findings under brake scope, not under oil change because that worksheet was open first.</p>
    <p>Separate safety-critical red items from monitor yellow — severity drives advisor present order and deferred follow-up.</p>
    <p>When you recommend a repair, name the component and failure mode — advisor will turn it into a repair action title.</p>

    <h3>After you save</h3>
    <p>Advisors review in estimate review mode before customer present — incomplete findings become incomplete estimates. See advisor guide <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'inspection-to-aro']) }}">Inspection to ARO</a>.</p>
    <p>If scope is wrong, leave an internal note on the RO — do not silently add findings to the wrong problem.</p>
    <p>Collaboration norms: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'worksheet-collaboration']) }}">Worksheet collaboration</a>.</p>

    <x-operations.learn.video
        role="technician"
        article="writing-findings"
        file="walkthrough.mp4"
        video-key="main"
        title="Write findings advisors can sell without rewrite"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Inspection flow: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'multi-point-inspection']) }}">Multi-point inspection</a>.</p>
    <p>RO status: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'ro-status']) }}">RO status for technicians</a>.</p>
</div>
