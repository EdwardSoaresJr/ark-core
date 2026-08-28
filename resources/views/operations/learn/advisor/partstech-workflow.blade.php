<div class="ops-learn-prose">
    <h3>Catalog from the RO</h3>
    <p>PartsTech opens from the active repair order — not a standalone parts browser. Prepare catalog from the RO, shop in PartsTech, then import the quote back into the scopes you are building.</p>
    <p>Each import lands as quotable lines you assign to repair actions. Matrix sell pricing applies on import when configured — advisors do not re-markup line by line on the floor.</p>
    <p>Admin credentials and vendor linkage live in Settings; advisors only need the workflow rhythm. Setup detail: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'partstech-setup']) }}">PartsTech setup</a>.</p>

    <x-operations.learn.figure
        role="advisor"
        article="partstech-workflow"
        file="partstech-import-preview.png"
        alt="PartsTech quote import preview mapped to repair actions"
        caption="Preview before import — wrong scope assignment is expensive to unwind at the counter."
    />

    <h3>How ARK knows which cart to pull</h3>
    <p>ARK tags each PartsTech cart with this repair order’s reference — usually <strong>R</strong> plus the shop RO number (example: RO 1522 → <strong>R1522</strong>). That same value is the PO / repair order number inside PartsTech.</p>
    <p>When you click <strong>Pull Quote</strong>, ARK logs into <strong>your</strong> PartsTech login (personal seat on your staff profile, or the shop default if you have none), finds the cart labeled for this RO, and imports the part lines (number, description, qty, cost, vendor). You choose which lines to import and assign each to a scope and repair action.</p>
    <p>ARK will refuse to pull the wrong RO’s cart. The PO field in PartsTech must show <strong>R{your RO number}</strong> — if it is blank or wrong, you are signed into a different PartsTech user than ARK used to prepare the cart.</p>

    <h3>Personal PartsTech logins (recommended for multi-advisor shops)</h3>
    <p>When each advisor has their own PartsTech user under the same shop, save username and password on your <strong>Profile</strong> (avatar menu → Profile). ARK prepares carts and pulls quotes with that login — Ben and Molly can shop different ROs at the same time without fighting over one shared cart.</p>
    <p><strong>Critical:</strong> the PartsTech tab in your browser must be signed in as the <strong>same</strong> user ARK uses for you. If ARK prepares as Ben but you browse PartsTech as the shop default, the RO number will not appear and pull will fail.</p>
    <ul>
        <li>Always click <strong>PartsTech</strong> from the RO you are quoting — never start from a generic PartsTech bookmark.</li>
        <li>Confirm the PO / RO field in PartsTech shows <strong>R{your RO number}</strong> before you trust the pull.</li>
        <li>After shopping, <strong>Pull Quote</strong> on that same RO right away.</li>
        <li>If ARK says PartsTech is busy on another RO, finish that cart (pull or clear) on the same PartsTech login, then retry.</li>
    </ul>

    <h3>Shared shop login only</h3>
    <p>If your shop uses <strong>one PartsTech login</strong> for everyone, PartsTech only keeps <strong>one active cart</strong> per account. Two advisors cannot reliably shop two ROs at once — finish one cart before another advisor opens PartsTech on a different RO.</p>

    <h3>Quote import discipline</h3>
    <p>Import preview shows vendor lines mapped to repair actions. Fix mapping before you commit — moving parts between scopes after import wastes time and confuses tech sheets.</p>
    <p>One repair action per named fix keeps tech and customer PDFs readable. Do not dump an entire cart into one action because it was faster in PartsTech.</p>
    <p>When catalog price differs from matrix sell, trust ARK totals — server authority recalculates from shop rules. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'parts-and-labor']) }}">Parts and labor entry</a>.</p>

    <h3>Procurement handoff</h3>
    <p>Imported parts still need honest procurement status when the bay is waiting. Flip waiting-parts posture only when the order is real — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'parts-procurement']) }}">Parts status and waiting-parts</a>.</p>
    <p>Technicians should see part names that match what will arrive — not internal vendor SKUs in customer-facing groups.</p>
    <p>Repeat catalog sessions for add-on work on the same RO; do not open a second RO because PartsTech timed out.</p>

    <x-operations.learn.video
        role="advisor"
        article="partstech-workflow"
        file="walkthrough.mp4"
        video-key="main"
        title="Open catalog, import quote, assign to scopes"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Line structure: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']) }}">Repair actions</a>.</p>
    <p>Matrix policy: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'parts-matrix-tune']) }}">Parts matrix tune</a>.</p>
</div>
