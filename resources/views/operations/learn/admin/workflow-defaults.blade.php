<div class="ops-learn-prose">
    <h3>Defaults shape every new RO</h3>
    <p>Workflow defaults in Settings set visit mode baseline, default recommendation intent, note privacy baseline, and operational toggles that advisors inherit on intake — not per-customer whims.</p>
    <p>Patch via <code>operations.settings.shop.workflow.update</code> — document shop decisions when you change them so advisors are not surprised mid-shift.</p>
    <p>Workflow defaults are policy, not shortcuts around authorization or matrix rules.</p>

    <x-operations.learn.figure
        role="admin"
        article="workflow-defaults"
        file="workflow-defaults-form.png"
        alt="Workflow defaults settings form"
        caption="Align defaults with how Demo Auto Repair actually writes estimates — not how software shipped."
    />

    <h3>Visit and note posture</h3>
    <p>Default visit mode (waiter vs drop-off) should match majority counter traffic — advisors override per RO when reality differs.</p>
    <p>Note privacy default should lean internal when unsure — advisors override per line with the Staff-only / Customer-visible toggle when adding notes. Advisor guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'note-privacy']) }}">Note privacy</a>.</p>
    <p>Recommendation intent defaults affect how deferred items present — coordinate with owner on inspection-heavy strategy.</p>

    <h3>Change discipline</h3>
    <p>Batch workflow changes in staff meeting — silent Friday edits cause Monday authorization confusion.</p>
    <p>After changes, spot-check one live intake and one inspection RO with senior advisor sign-off.</p>
    <p>Related: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'visit-posture']) }}">Visit posture</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']) }}">Service counter check-in</a>.</p>

    <h3>Related guides</h3>
    <p>Financial layers: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'financial-rules']) }}">Financial rules</a>.</p>
    <p>Owner targets: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'owner-targets']) }}">Owner targets</a>.</p>
</div>
