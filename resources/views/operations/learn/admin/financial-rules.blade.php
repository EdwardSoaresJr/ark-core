<div class="ops-learn-prose">
    <h3>Financial authority lives in Settings</h3>
    <p>Tax, shop fees, labor rates, parts matrices, and estimate disclaimers combine into every RO total through server calculators — not advisor mental math. Admin changes here affect new lines immediately; existing RO snapshots may need conscious refresh per shop policy.</p>
    <p>Open <a href="{{ route('operations.settings.shop.edit') }}">Settings</a> sections: Labor, Tax, Shop fees, Parts matrices, Estimates, Payments.</p>
    <p>One authoritative path — never “fix” totals in spreadsheets parallel to ARK.</p>

    <x-operations.learn.figure
        role="admin"
        article="financial-rules"
        file="settings-financial-sections.png"
        alt="Settings navigation for labor tax fees and parts matrices"
        caption="Change matrices in daylight with owner present — midnight markup edits confuse tomorrow’s counter."
    />

    <h3>Labor and parts</h3>
    <p>Default labor rate and categories set advisor line defaults — categories must match how the shop actually bills (diag, maintenance, engine, etc.).</p>
    <p>Parts matrices enforce sell pricing from cost — advisors should not discount matrix output routinely. Owner tune guide: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'parts-matrix-tune']) }}">Parts matrix tune</a>.</p>
    <p>Shop overhead and loaded cost: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'shop-overhead-setup']) }}">Shop overhead setup</a>.</p>

    <h3>Tax, fees, estimates</h3>
    <p>Tax rules must match how the shop files — ARK calculates from configured rates and taxable flags on line types.</p>
    <p>Shop fees (shop supplies, environmental) belong in fee settings, not smuggled as fake parts lines.</p>
    <p>Estimate disclaimers and validity language protect authorization disputes — align wording with owner legal preference once, then stop editing weekly.</p>

    <h3>Payments configuration</h3>
    <p>External payment recording — see <a #>Payment recording</a>.</p>
    </div>
