<div class="ops-learn-prose">
    <h3>Vehicle on lot is a fact</h3>
    <p>Visit posture records whether the vehicle is physically at the shop, dropped off, waiting on customer, or gone — independent of estimate completion. Lifecycle says where the job is in workflow; visit posture says where the car is on earth.</p>
    <p>Update visit posture from the RO when keys change hands — not at end of day batch cleanup. Tow-ins and after-hours drops need honest posture even if no advisor is at the counter.</p>
    <p>Route: <code>operations.repair-orders.visit-posture.update</code> — quick toggle on workspace, server authoritative.</p>

    <x-operations.learn.figure
        role="advisor"
        article="visit-posture"
        file="visit-posture-control.png"
        alt="Visit posture selector on repair order workspace"
        caption="Mismatch example: car marked gone while lifecycle still in progress — customer will arrive angry."
    />

    <h3>Operational use</h3>
    <p>Workboard and bay planning assume dropped-off cars are on lot — wrong posture starves dispatch priority.</p>
    <p>Customer waiting while you quote stays a different posture than overnight drop — communicate pickup expectations accordingly.</p>
    <p>Visit mode at intake (waiter vs drop-off) seeds defaults; posture updates refine as the visit evolves.</p>

    <h3>Defaults and settings</h3>
    <p>Shop workflow defaults set visit mode baseline for new ROs — admin maintains policy in Settings. See <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'workflow-defaults']) }}">Workflow defaults</a>.</p>
    <p>Check-in recognition still drives customer and vehicle — posture does not replace concern capture. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']) }}">Service counter check-in</a>.</p>
    <p>Lifecycle handoff when car arrives for authorized work: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'lifecycle-transitions']) }}">Lifecycle transitions</a>.</p>

    <h3>Related guides</h3>
    <p>Workboard scan: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workboard-lanes']) }}">Workboard lanes</a>.</p>
</div>
