<div class="ops-learn-prose">
    <h3>Operational Report</h3>
    <p><a href="{{ route('operations.reports.operational') }}">Operations → Operational Report</a>. Calm executive pulse — not dashboard theater. Two top-line numbers matter most: <strong>Sales Posted</strong> (revenue recognized) and <strong>Cash Collected</strong> (money in the drawer). They will not always match in the same day — that is normal; reconciliation explains why.</p>

    <h3>Report tabs</h3>
    <dl>
        <dt>Operations</dt>
        <dd>Queue pressure, approval momentum, liability, recommendation conversion.</dd>
        <dt>Margin Health</dt>
        <dd>Parts margin, ELR, ARO, and sales mix vs shop targets — with next actions. Break-even pulse when monthly fixed costs are set.</dd>
        <dt>Owner P&amp;L</dt>
        <dd>Management P&amp;L from posted RO truth: service revenue, COGS, gross profit, prorated operating expenses, estimated operating income, tax remittance posture, and 20% net profit benchmark. Reconcile with bookkeeper — not authoritative accounting.</dd>
        <dt>Financial</dt>
        <dd>Posted RO summary, payments reconciliation (cash ↔ posted sales), category mix, deferred work, recent closures.</dd>
        <dt>Production</dt>
        <dd>Live pressure, technician efficiency, advisor throughput.</dd>
    </dl>

    <h3>Executive Pulse</h3>
    <p>Top-line KPIs for the selected date range. <strong>Opened</strong> metrics use intake date. <strong>Posted</strong> metrics use <code>posted_at</code> — when the RO was posted to operational reporting (Tekmetric EOD alignment).</p>
    <dl>
        <dt>Sales Posted</dt>
        <dd>Financial truth — sold work on ROs <em>posted</em> in range. Margin KPIs (ELR, parts margin, labor margin, mix) use posted sales only.</dd>
        <dt>Cash Collected</dt>
        <dd>Payments and deposits cashiered in range — Tekmetric-aligned cash truth. Compare to Sales Posted on the Financial tab.</dd>
        <dt>Car Count</dt>
        <dd>ROs opened in range — shop volume.</dd>
        <dt>ARO</dt>
        <dd>Average total value per opened RO (open + closed value).</dd>
        <dt>Labor Sold</dt>
        <dd>Posted labor revenue and billed hours in range.</dd>
        <dt>Parts Sold</dt>
        <dd>Posted parts revenue in range.</dd>
        <dt>Fees Sold</dt>
        <dd>Shop fees and fee lines on posted ROs in range.</dd>
        <dt>Effective Labor Rate</dt>
        <dd>Labor sold ÷ billed hours on posted sales. Compare to posted rate.</dd>
        <dt>Parts Margin</dt>
        <dd>Parts gross profit % on posted sales (known costs only).</dd>
        <dt>Labor Margin</dt>
        <dd>Labor gross profit % on posted sales (partial cost model).</dd>
        <dt>Parts/Labor Mix</dt>
        <dd>Share of posted sales dollars — target ~45% parts / 55% labor.</dd>
        <dt>Deferred Opportunity</dt>
        <dd>Deferred work dollars — follow-up revenue in customer history.</dd>
        <dt>Unpaid Pickups</dt>
        <dd>Ready for pickup but not collected — cash risk.</dd>
    </dl>

    <h3>Financial tab — payments reconciliation</h3>
    <p>Open <a href="{{ route('operations.reports.operational', ['tab' => 'financial']) }}">Operational Report → Financial</a>. The reconciliation block bridges <strong>Cash Collected</strong> to <strong>Sales Posted</strong>:</p>
    <ul>
        <li><strong>Total cashiered</strong> — all payments in range (matches Cash Collected KPI).</li>
        <li><strong>Advance pay</strong> — cash collected on ROs not yet posted in range (customer paid early).</li>
        <li><strong>Previous advanced pay</strong> — payments in range on ROs posted in range from earlier deposits.</li>
        <li><strong>Cleared from A/R</strong> — payments that clear posted sales in range.</li>
        <li><strong>Legacy carryover excluded</strong> — ROs posted in range but outside Sales Posted scope (opened before reporting floor).</li>
        <li><strong>Reconciled</strong> — should equal Sales Posted when the day foots.</li>
    </ul>
    <p>Each line expands to show repair orders — click through to the RO when a bucket looks wrong. Full walkthrough: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'payments-reconciliation']) }}">Payments reconciliation</a>.</p>

    <h3>Production tab</h3>
    <p>Queue pressure (approvals, parts, unpaid pickup, ready execution) and <strong>technician efficiency</strong> — billed hours vs weekday capacity.</p>

    <h3>Post workflow (advisor handoff)</h3>
    <p>Advisors post ROs from the financial rail when invoice is ready — <strong>Post Repair Order</strong> records <code>posted_at</code> and includes the RO in Sales Posted. Close — Paid posts automatically. Unposted closed work does not appear in owner KPIs.</p>

    <h3>Target hints</h3>
    <p>Green and amber hints on margin KPIs reflect targets in <strong>Settings → Owner Targets &amp; Reporting</strong>. Update there as shop targets evolve.</p>

    <h3>Owner digest email</h3>
    <p>When enabled, admins receive a daily email with Sales Posted, Cash Collected, reconciliation status, queue pressure, and links to Financial tab and Day Review. Schedule lives in Owner Targets settings.</p>

    <p>Industry context: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-kpis']) }}">KPIs to review daily</a> · <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'weekly-owner-review']) }}">Weekly owner review</a>.</p>
</div>
