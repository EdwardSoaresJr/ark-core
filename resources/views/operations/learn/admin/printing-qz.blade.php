<div class="ops-learn-prose">
    <h3>QZ Tray for silent labels</h3>
    <p>Key tags and oil change stickers print through QZ Tray signing from the browser — advisors click print on the RO; QZ talks to the local label printer without PDF save dialogs.</p>
    <p>Admin installs QZ Tray on counter workstations once, then trusts signing certificate via ARK <code>operations.printing.qz.sign</code> flow.</p>
    <p>Health endpoint <a href="{{ route('operations.printing.health') }}">printing health</a> surfaces broken signing before counter rush.</p>

    <x-operations.learn.figure
        role="admin"
        article="printing-qz"
        file="qz-trust-prompt.png"
        alt="QZ Tray certificate trust during ARK signing setup"
        caption="Trust once per workstation — document reinstall steps for new PCs."
    />

    <h3>Configuration in Settings</h3>
    <p>Settings → Printing defines printer names QZ expects — must match OS printer queue names exactly.</p>
    <p>Test key tag and oil sticker from a training RO before go-live — alignment beats speed on first install.</p>
    <p>PDF fallbacks (tech sheet, estimate) do not require QZ — only label paths do.</p>

    <h3>Troubleshooting</h3>
    <p>Sign health route verifies certificate chain — rerun after QZ upgrade or macOS permission reset.</p>
    <p>If labels print blank, check label stock size in driver vs ARK template — software rarely fixes wrong hardware profile.</p>
    <p>Advisor usage: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ro-printing']) }}">RO printing</a>.</p>

    <x-operations.learn.video
        role="admin"
        article="printing-qz"
        file="walkthrough.mp4"
        video-key="main"
        title="Install QZ, trust cert, test key tag print"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Admin overview: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'getting-started']) }}">Admin getting started</a>.</p>
</div>
