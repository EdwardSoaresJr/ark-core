<div class="ops-learn-prose">
    <h3>PartsTech is shop-wide plumbing</h3>
    <p>PartsTech credentials and shop linkage live in <a href="{{ route('operations.settings.shop.edit', ['section' => 'partstech']) }}">Settings → PartsTech</a>. Advisors never re-enter API keys on the floor.</p>
    <p>When PartsTech is down, advisors can still hand-enter parts — but catalog import is the default path for speed and margin accuracy.</p>
    <p>Test import on a sandbox RO after any credential rotation before Monday counter opens.</p>

    <x-operations.learn.figure
        role="admin"
        article="partstech-setup"
        file="partstech-settings.png"
        alt="PartsTech settings with connection status"
        caption="Green connection status before you train advisors on catalog workflow."
    />

    <h3>Shop default and per-advisor logins</h3>
    <p>Settings → PartsTech stores the shop username, API key (VIN decode), and a default password for cart prep and quote import.</p>
    <p>For multi-advisor shops, each advisor adds their personal PartsTech user on their <strong>Profile</strong> (avatar menu → Profile → PartsTech login). ARK uses that login when they open PartsTech or pull a quote; leave blank to fall back to the shop default.</p>
    <p>Each repair order gets a PartsTech cart reference: <strong>R</strong> + shop RO number (RO 1522 → <strong>R1522</strong>). ARK sets that on the cart before catalog launch and uses it to find the right quote on import.</p>
    <p>Advisors must browse PartsTech signed in as the same user ARK uses for their profile. Mismatched logins are the most common reason the RO/PO field stays blank and pull fails.</p>
    <p>With separate PartsTech users, each advisor has their own active cart — Ben and Molly can shop different ROs concurrently. With only the shared shop login, train one RO at a time. Advisor guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'partstech-workflow']) }}">PartsTech workflow</a>.</p>

    <h3>Setup sequence</h3>
    <p>Enter PartsTech shop credentials from vendor portal — store ID and API keys per PartsTech documentation for your account tier.</p>
    <p>Confirm preferred suppliers and default matrix behavior with owner — pricing surprises are policy failures, not advisor mistakes.</p>
    <p>Walk one quote end-to-end: prepare catalog from RO, shop cart, preview import, commit lines — advisor guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'partstech-workflow']) }}">PartsTech workflow</a>.</p>
    <p>Walk a second RO only <strong>after</strong> the first cart is pulled or cleared — verify ARK blocks or warns when a foreign cart still has parts.</p>

    <h3>Ongoing maintenance</h3>
    <p>Rotate credentials when staff with vendor login leaves — same discipline as Square and Twilio.</p>
    <p>When import mapping breaks after ARK update, capture one failing RO for dev — do not train workarounds that skip preview.</p>
    <p>When advisors report “wrong parts on pull,” ask whether two ROs were open in PartsTech at once before assuming a software bug.</p>
    <p>Matrix policy: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'parts-matrix-tune']) }}">Parts matrix tune</a>.</p>

    <x-operations.learn.video
        role="admin"
        article="partstech-setup"
        file="walkthrough.mp4"
        video-key="main"
        title="Connect PartsTech and test quote import"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Financial: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'financial-rules']) }}">Financial rules</a>.</p>
</div>
