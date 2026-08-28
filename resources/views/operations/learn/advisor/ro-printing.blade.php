<div class="ops-learn-prose">
    <h3>Print is operational handoff</h3>
    <p>RO printing delivers what leaves the shop on paper — intake sheets, tech production PDFs, key tags, oil stickers, customer estimates. Print from the RO print menu; do not screenshot the screen for bay use.</p>
    <p>QZ Tray signing enables silent label printers at the counter — admin configures once; advisors click print and walk away. See <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'printing-qz']) }}">Printing and QZ Tray</a>.</p>
    <p>When QZ is unhealthy, ARK surfaces print health — switch to PDF download temporarily rather than skipping documentation.</p>

    <x-operations.learn.figure
        role="advisor"
        article="ro-printing"
        file="ro-print-menu.png"
        alt="Repair order print menu with sheets tags and estimate PDF"
        caption="Tech sheet PDF matches authorized lines — reprint after authorization changes."
    />

    <h3>What to print when</h3>
    <p><strong>Check In sheet</strong> — vehicle and concern at keys drop-off; optional if digital-first shop, required if bay asks for paper.</p>
    <p><strong>Tech production sheet</strong> — authorized scope summary for the bay; reprint when lines change after dispatch.</p>
    <p><strong>Key tag / oil sticker</strong> — QZ labels at write-up; verify year/make on tag before affixing.</p>
    <p><strong>Estimate PDF</strong> — customer signature path or mail; email estimate action uses same authoritative document pipeline.</p>

    <h3>Estimate documents</h3>
    <p>Customer estimate PDFs and email attachments exclude <strong>draft</strong> scopes — only presentable scopes cross the customer boundary. Do not hand customers unsigned builder noise.</p>
    <p>Remote sell: email sends PDF + portal link from the same snapshot pipeline as print/download. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>
    <p>Download vs print vs email is delivery choice; content authority is the same snapshot pipeline.</p>
    <p>Related lifecycle: authorization before tech sheet, invoice after complete — <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="ro-printing"
        file="walkthrough.mp4"
        video-key="main"
        title="Print tech sheet, key tag, and estimate PDF"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Tech consumption: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'tech-production-sheet']) }}">Tech production sheet</a>.</p>
    <p>Print health: <a href="{{ route('operations.printing.health') }}">Printing health</a> (admin troubleshooting).</p>
</div>
