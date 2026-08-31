<div class="ops-learn-prose">
    <h3>Owner targets in Settings</h3>
    <p>Shop excellence targets live in <strong>Settings → Owner Targets &amp; Reporting</strong> — gross margin bands, ELR expectations, car count goals, owner digest schedule. Patch via <code>operations.settings.shop.excellence.update</code>.</p>
    <p>Targets are this shop’s numbers — industry benchmarks stay in docs, not hardcoded surprises in reports.</p>
    <p>Quarterly owner review marks target refresh complete — paid coaching notes stay in private docs, not customer-visible fields.</p>

    <x-operations.learn.figure
        role="admin"
        article="owner-targets"
        file="owner-targets-form.png"
        alt="Owner targets settings with margin and ELR bands"
        caption="Set targets with owner in room — admins enter, owners own the numbers."
    />

    <h3>What targets drive</h3>
    <p>Operational Report and Margin Health compare posted RO truth to configured bands — open queue metrics labeled separately.</p>
    <p>Owner digest email sends to active admins when enabled at configured shop time — includes Sales Posted, Cash Collected, reconciliation status, queue pressure, and Financial tab link. Verify recipients after staff changes.</p>
    <p>Break-even and overhead tie to shop overhead worksheet — <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'shop-overhead-setup']) }}">Shop overhead setup</a>.</p>

    <h3>Admin habits</h3>
    <p>Do not change targets mid-week to greenwash a bad month — owners lose trust in the product.</p>
    <p>After target edits, walk owner through <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-kpis']) }}">Daily KPIs</a> and <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'payments-reconciliation']) }}">Payments reconciliation</a> so report labels match conversation.</p>
    <p>Owner guides: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'quarterly-target-review']) }}">Quarterly target review</a>, <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'bookend-walkthrough']) }}">Day Review walkthrough</a>.</p>

    <h3>Related guides</h3>
    <p>Financial rules: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'financial-rules']) }}">Financial rules</a>.</p>
    <p>Parts matrix: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'parts-matrix-tune']) }}">Parts matrix tune</a>.</p>
</div>
