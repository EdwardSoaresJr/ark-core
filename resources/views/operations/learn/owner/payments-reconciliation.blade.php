<div class="ops-learn-prose">
    <h3>Why cash and posted sales differ</h3>
    <p>Healthy shops track both <strong>Cash Collected</strong> (money in) and <strong>Sales Posted</strong> (revenue recognized). Same-day totals often diverge — deposits before post, payments on yesterday’s invoice, legacy RO rules. ARK’s reconciliation on the Financial tab explains the gap instead of hiding it.</p>

    <h3>Where to open it</h3>
    <p><a href="{{ route('operations.reports.operational', ['tab' => 'financial']) }}">Operational Report → Financial</a>. Pick the date range (single day for EOD, week for owner review). Scroll to <strong>Payments Reconciliation</strong> below Posted RO Summary.</p>

    <h3>Reading each line</h3>
    <dl>
        <dt>Total cashiered</dt>
        <dd>Sum of payments and deposits in range. Must match the <strong>Cash Collected</strong> KPI on Executive Pulse.</dd>
        <dt>Advance pay</dt>
        <dd>Money collected in range on ROs not yet posted (or posted after range end). Customer paid before EOD post — cash today, sales tomorrow.</dd>
        <dt>Previous advanced pay</dt>
        <dd>Payments in range that apply to ROs posted in range, where part of the payment was an earlier deposit. Adjusts double-count between cash and posted buckets.</dd>
        <dt>Cleared from A/R</dt>
        <dd>Payments in range that clear posted invoice totals for ROs posted in range.</dd>
        <dt>Legacy carryover excluded</dt>
        <dd>ROs posted in range but excluded from Sales Posted (e.g. opened before reporting trust floor). Cash may still have moved — this line explains posted sales below cashiered without treating it as “missing money.”</dd>
        <dt>Reconciled</dt>
        <dd>Should equal <strong>Sales Posted</strong> when the period foots. If not, expand drill-down rows and find the RO.</dd>
    </dl>

    <h3>Drill-down habit</h3>
    <p>Every non-zero bucket expands to repair order links — customer, vehicle, amount, payment/posted timestamps. Click the RO, verify invoice and payments on the financial rail, then post or adjust if the advisor skipped EOD post.</p>
    <p>Common fixes: post the RO (<strong>Post to sales</strong> on Closeout), record a missing payment, or confirm a deposit was applied to the right invoice.</p>

    <h3>Daily owner rhythm</h3>
    <ol>
        <li>End of day: check reconciliation for today — does it foot?</li>
        <li>If gap: one RO drill-down, not spreadsheet archaeology.</li>
        <li>Day Review queue pressure separately — reconciliation is numbers; Day Review is unfinished work.</li>
    </ol>
    <p>Daily digest email repeats Sales Posted, Cash Collected, and reconcile yes/no — use it as a nudge if you close the day late. Configure in <strong>Settings → Owner Targets &amp; Reporting</strong>.</p>

    <h3>Advisor accountability</h3>
    <p>Train advisors: invoice → collect → <strong>post</strong> before they leave. Unposted work makes Sales Posted lie and forces owner detective work. Guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits, invoice, and closeout</a>.</p>

    <p>Definitions: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'ark-reports-guide']) }}">What ARK reports mean</a> · Weekly: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'weekly-owner-review']) }}">Weekly owner review</a>.</p>
</div>
