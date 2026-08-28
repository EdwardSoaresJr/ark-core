<div class="ops-learn-prose">
    <h3>Matrix is margin discipline</h3>
    <p>Parts sell price should come from matrix rules on cost — not advisor discount habit at the counter. Target band ~55–58% parts margin on posted work when matrix is tuned honestly.</p>
    <p>Owner tune surface: <a href="{{ route('operations.owner.parts-matrix-tune') }}">Parts matrix tune</a> — review tiers with admin in Settings → Parts matrices.</p>
    <p>Matrix changes affect new imports and recalculations — communicate before mid-week edits surprise advisors.</p>

    <x-operations.learn.figure
        role="owner"
        article="parts-matrix-tune"
        file="matrix-tiers.png"
        alt="Parts matrix tiers by cost band with markup multipliers"
        caption="Low-cost hardware items often need higher multiplier — pennies lost multiply across car count."
    />

    <h3>Review questions</h3>
    <p>Are high-volume SKUs (filters, pads, wipers) hitting target margin after vendor price updates?</p>
    <p>Are advisors routinely overriding sell price — symptom of wrong matrix tier or training gap, not “bad advisors”?</p>
    <p>Does PartsTech import respect matrix on commit — test one RO after any matrix edit.</p>

    <h3>Connect to excellence</h3>
    <p>Parts margin appears in daily KPIs and operational report — label posted RO source when discussing with team.</p>
    <p>Admin entry: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'financial-rules']) }}">Financial rules</a>.</p>
    <p>Advisor behavior: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'parts-and-labor']) }}">Parts and labor entry</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'partstech-workflow']) }}">PartsTech workflow</a>.</p>

    <x-operations.learn.video
        role="owner"
        article="parts-matrix-tune"
        file="walkthrough.mp4"
        video-key="main"
        title="Review matrix tiers against last month posted GP"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Five steps: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'shop-margins-five-steps']) }}">Shop margins five steps</a>.</p>
    <p>Targets: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'owner-targets']) }}">Owner targets</a>.</p>
</div>
