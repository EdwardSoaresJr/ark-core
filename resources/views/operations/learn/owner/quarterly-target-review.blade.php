<div class="ops-learn-prose">
    <h3>Quarterly target review</h3>
    <p>Industry benchmarks live in doctrine docs. <strong>Your</strong> bands live in <strong>Settings → Owner Targets &amp; Reporting</strong> — ARK report hints and owner digest read from shop settings. Review quarterly so green/amber on Margin Health stays honest as the shop grows.</p>

    <h3>When to run it</h3>
    <ul>
        <li>End of each fiscal quarter, or after a major change (new bay, rate increase, new billing class).</li>
        <li>Pair with your accountant’s P&amp;L — ARK Sales Posted should directionally match revenue.</li>
        <li>Block 45–60 minutes; this is owner work, not a staff meeting.</li>
    </ul>

    <h3>Checklist</h3>
    <ol>
        <li><strong>Posted labor rate</strong> — match Shop Settings; did you raise in small steps this quarter?</li>
        <li><strong>ELR floor</strong> — set <code>effective_labor_rate_floor_cents</code> to the minimum acceptable $/hr on posted work (often slightly below posted rate).</li>
        <li><strong>ARO target</strong> — general repair shops often benchmark ~$750; adjust if your mix is fleet, euro, or heavy diesel.</li>
        <li><strong>Parts margin target</strong> — default 55%; raise only if matrix and mix support it.</li>
        <li><strong>Labor/parts mix</strong> — balanced model ~55% labor / 45% parts on posted sales.</li>
        <li><strong>Digest schedule</strong> — confirm <code>owner_digest_time</code> and recipients match when you run Day Review.</li>
    </ol>

    <h3>After you edit targets</h3>
    <ol>
        <li>Set <code>last_target_review</code> to today (YYYY-MM-DD) in the same file.</li>
        <li>Open <a href="{{ route('operations.reports.operational', ['tab' => 'margin-health']) }}">Margin Health</a> for the last 30 days — do bands feel right?</li>
        <li>Spot-check Financial tab reconciliation for a recent week — numbers should still foot when advisors post consistently.</li>
        <li>Pick <strong>one</strong> implementation commitment for next quarter (rate step, matrix row, inspection standard).</li>
    </ol>

    <p>Weekly rhythm: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'weekly-owner-review']) }}">Weekly owner review</a>. Daily habit: <a href="{{ route('operations.owner.day-review') }}">Day Review</a>.</p>
</div>
