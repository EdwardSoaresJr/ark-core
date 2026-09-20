<div class="ops-learn-prose">
    <h3>Notes have audiences</h3>
    <p>ARK separates customer-visible narrative from internal shop notes — advisors control what appears on PDFs and portal vs what stays on the floor. Wrong privacy burns trust when internal shorthand reaches the customer.</p>
    <p>Default note privacy comes from shop workflow settings — admins set baseline; advisors override per note when policy allows.</p>
    <p>When in doubt, internal — you can promote to customer-visible later; you cannot un-ring a rude internal joke on a printed estimate.</p>

    <x-operations.learn.figure
        role="advisor"
        article="note-privacy"
        file="note-privacy-toggle.png"
        alt="Note privacy toggle between internal and customer visible"
        caption="Customer-visible notes should read professionally — no abbreviations the customer did not use."
    />

    <h3>Where privacy matters</h3>
    <p>Scope findings and recommendations often customer-facing — write as if the customer reads them tonight on the portal link.</p>
    <p><strong>Draft</strong> scopes never reach customer surfaces — PDF, portal, email, and intake sheets hide draft work entirely. Only presentable scopes cross the customer boundary.</p>
    <p>Line notes on the worksheet use three audience checkboxes: <strong>Advisor</strong> (default), <strong>Technician</strong> (tech sheet), and <strong>Customer</strong> (estimate PDF and portal). Default comes from shop workflow settings; override per note when adding or editing. Use <strong>Note</strong> for floor context — there is no separate Advisor Note field on the scope.</p>
    <p>Internal notes for bay drama, vendor arguments, and collection reminders — never customer-visible.</p>
    <p>Handoff notes between advisors may project to attention queue — still not customer PDF content.</p>

    <h3>Training habits</h3>
    <p>New advisors: read every customer-visible sentence aloud before send estimate link — awkward phrasing catches here.</p>
    <p>Technicians writing findings should assume advisor may promote text — factual, no shop slang. See <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'writing-findings']) }}">Writing findings</a>.</p>
    <p>Admin policy: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'workflow-defaults']) }}">Workflow defaults</a>.</p>

    <h3>Related guides</h3>
    <p>Estimate present: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'estimate-review-mode']) }}">Estimate review mode</a>.</p>
    <p>Texting: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a> — thread is customer-visible by definition.</p>
</div>
