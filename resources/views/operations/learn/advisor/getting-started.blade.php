<div class="ops-learn-prose">
    <h3>Your job in ARK</h3>
    <p>You turn customer visits into clear repair orders and sellable estimates. ARK is built for counter speed — recognize the customer, open the RO, build the work, get approval.</p>

    <h3>Daily rhythm</h3>
    <ol>
        <li><strong>Work</strong> — customer decisions, comms recovery, and shop pressure before you dive into ROs (rose bar when customers need you).</li>
        <li><strong>Workboard</strong> — live lane detail when you need dispatch and bay posture.</li>
        <li><strong>+ Check In</strong> — open a repair order when the customer is on the phone or at the counter.</li>
        <li><strong>Customers</strong> — hub for history, Comms timeline, vehicles, and contact info.</li>
        <li><strong>Workspace tabs</strong> — keep multiple ROs open; yellow bars and dots tell you what needs attention. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workspace-tabs']) }}">Workspace tabs</a>.</li>
    </ol>

    <x-operations.learn.figure
        role="advisor"
        article="getting-started"
        file="daily-rhythm.png"
        alt="Advisor daily rhythm across workboard, intake, and customers"
        caption="Counter rhythm: pressure map first, then recognition, then build."
    />

    <h3>Opening a repair order</h3>
    <p>Check In asks three things you already know:</p>
    <ul>
        <li><strong>Who</strong> is this?</li>
        <li><strong>What vehicle?</strong></li>
        <li><strong>Why are they here?</strong></li>
    </ul>
    <p>Enter the customer concern in plain language — the same words you would use on the phone. ARK creates the repair order and first scope from that.</p>

    <h3>After the RO opens</h3>
    <p>Land on the estimate worksheet. For each problem:</p>
    <ol>
        <li><strong>Scope</strong> — name the problem (<em>Overheating</em>), not the repair (<em>Replace radiator</em>).</li>
        <li><strong>Repair actions</strong> — one named fix per action (<em>Replace Water Pump</em>).</li>
        <li><strong>Labor</strong> — billed operation inside that action; usually matches the action title.</li>
    </ol>
    <p>Read <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']) }}">Repair actions — scope vs repair action vs labor</a> before building your first grouped estimate.</p>
    <p>Remote customers: present from review mode, then send portal link by email or text — <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>
</div>
