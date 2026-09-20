<div class="ops-learn-prose">
    <h3>Deposits before exotic work</h3>
    <p>Deposits protect the shop on special-order parts and long diagnostic commits. Record deposit on the RO when money changes hands — not in a side spreadsheet.</p>
    <p>Counter deposits use the financial rail on the RO. Record cash, check, or card taken on an external terminal the same way — Core owns the ledger, not the processor.</p>
    <p>Portal balance links can show amount due; online card capture belongs to future ARK Platform Payments, not Core.</p>
    <p>Deposit updates adjust authorized balance due; totals remain server-authoritative through invoice generation. Never mentally subtract in Quick Reply payment messages.</p>
    <p>Shop policy on deposit percentage lives in training and owner targets — ARK records what you actually collected.</p>

    <x-operations.learn.figure
        role="advisor"
        article="deposits-and-invoicing"
        file="deposit-invoice-rail.png"
        alt="Deposit capture and invoice issuance on repair order financial rail"
        caption="Invoice issues from completed authorized work — not from draft estimate lines."
    />

    <h3>Invoicing rhythm</h3>
    <p>Issue invoice when work is complete and lines match what was performed — not what was quoted if scope changed. Adjust lines in build mode with notes before invoice store.</p>
    <p>Invoice store is a deliberate action — review totals rail, tax, fees, and payments applied. Posted invoice is prerequisite for EOD post.</p>
    <p>Partial payments and balance due flow through payment recording on the RO financial rail.</p>

    <h3>Post for reporting truth</h3>
    <p>After invoice and payment are correct, use <strong>Post to sales</strong> on Closeout. That sets <code>posted_at</code> and includes the RO in owner <strong>Sales Posted</strong> KPIs. Closing as Paid posts automatically.</p>
    <p>Cash collected without post still shows in <strong>Cash Collected</strong> — owner reconciliation will show advance pay until you post. Post before you leave when the customer paid.</p>

    <h3>Payments on the floor</h3>
        <p>Remote balance: portal payment link via Quick Reply — <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'portal-payment-links']) }}">Portal payment links</a>.</p>
    <p>Owner setup: <a #>Payment recording</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="deposits-and-invoicing"
        file="walkthrough.mp4"
        video-key="main"
        title="Deposit, invoice, post, and balance due handoff"
        poster-file="poster.jpg"
    />

    <h3>Reporting truth</h3>
    <p>Posted RO sales feed owner KPIs — open queue dollars are workflow truth, not Sales Posted. Unposted invoices do not count in margin reports.</p>
    <p>Related: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-kpis']) }}">Daily KPIs</a>, <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'payments-reconciliation']) }}">Payments reconciliation</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>
</div>
