@php
    $paymentsSettings = route('operations.settings.shop.edit', ['section' => 'payments']);
    $squareWebhook = route('webhooks.square');
@endphp

<div class="ops-learn-prose">
    <h3>What you are setting up</h3>
    <p>Square in ARK is a <strong>card capture rail</strong> — not your shop system of record. Amounts always come from ARK balance due; successful charges post to the RO ledger like a manual card payment, with a traceable Square payment ID.</p>
    <p>Operational panel: <a href="{{ $paymentsSettings }}">Settings → Shop → Payments</a>.</p>

    <h3>How this differs from Square Register alone</h3>
    <ul>
        <li><strong>Before</strong> — ring up payment in the Square app on the reader; reconcile to the RO manually.</li>
        <li><strong>Now</strong> — advisor clicks <strong>Charge card</strong> on the RO financial rail; ARK pushes the amount to the reader; ledger updates when Square completes.</li>
    </ul>
    <p>The hardware is the same. What changes is <strong>which app controls the reader</strong> and <strong>where charges start</strong>.</p>

    <h3 id="reader-connected-mode">Critical: two modes on the physical reader</h3>
    <p>Before you paste a device ID or test ARK, confirm the reader is in the correct mode. This is the most common reason terminal capture fails.</p>

    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left">
                <th class="py-2 pr-3 font-bold text-slate-950">Mode</th>
                <th class="py-2 pr-3 font-bold text-slate-950">What you see on the reader</th>
                <th class="py-2 font-bold text-slate-950">ARK Charge card</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-slate-100">
                <td class="py-2 pr-3 font-semibold text-slate-900">Square Register / POS</td>
                <td class="py-2 pr-3 text-slate-700">Full Square register — item library, open tickets, normal Square checkout</td>
                <td class="py-2 font-semibold text-rose-800">Does not work</td>
            </tr>
            <tr>
                <td class="py-2 pr-3 font-semibold text-slate-900">Connected / Terminal API</td>
                <td class="py-2 pr-3 text-slate-700">Idle waiting screen (often dark/minimal) — reader waits for ARK to send a checkout</td>
                <td class="py-2 font-semibold text-emerald-800">Works</td>
            </tr>
        </tbody>
    </table>

    <p><strong>ARK only works in Connected / Terminal API mode.</strong> If staff normally run sales on the Square app built into the reader, ARK cannot push charges until you switch modes.</p>

    <h3>Owner checklist (in order)</h3>
    <ol>
        <li><strong>Square credentials</strong> — <a href="{{ $paymentsSettings }}">Settings → Payments</a>, Environment = <strong>production</strong>, Location ID saved.</li>
        <li><strong>Generate pairing code in ARK</strong> — same page, <strong>Terminal pairing</strong> panel → <strong>Generate pairing code</strong> (separate button — not Save Square settings).</li>
        <li><strong>Reset the reader if needed, then sign in with Device Code</strong> — see Step 4. A full reader reset was required at Auto Repair Keeper when the device had been used in Square Register mode.</li>
        <li><strong>Square Developer webhook</strong> — include <code>device.code.paired</code> and <code>payment.updated</code>.</li>
        <li><strong>Save Square settings</strong> — confirm Terminal device ID (ARK auto-fills after pairing), enable Counter terminal.</li>
        <li><strong>Verify</strong> — small live charge on a RO with issued invoice and balance due.</li>
    </ol>

    <h3>Step 1 — Square credentials (Settings)</h3>
    <p><a href="{{ $paymentsSettings }}">Settings → Shop → Payments</a> → Application ID, Access Token, Location ID, Webhook signature key, and environment (sandbox / production). Secrets are encrypted in the shop database.</p>
    <p>Server <code>.env</code> Square vars still work as a fallback for existing deploys — paste credentials in Settings to stop editing files over SSH.</p>
    <p>All three must belong to the <strong>same Square merchant account</strong>: access token, location ID, and the reader you pair.</p>

    <h3>Step 2 — Square Developer webhook</h3>
    <p>Square Developer Dashboard → your application → Webhooks → Add subscription:</p>
    <ul>
        <li><strong>URL</strong> — <code>{{ $squareWebhook }}</code></li>
        <li><strong>Events</strong> — <code>payment.updated</code> (ARK completes ledger writes when status is COMPLETED), <code>device.code.paired</code> (reader finished Terminal API pairing), and <code>terminal.checkout.updated</code> when available.</li>
    </ul>
    <p>Copy the <strong>signature key</strong> into Settings → Payments → Webhook signature key.</p>

    <h3>Step 3 — Settings → Payments (credentials + surfaces)</h3>
    <p><a href="{{ $paymentsSettings }}">Settings → Shop → Payments</a> — the page has <strong>two actions</strong>: <strong>Generate pairing code</strong> (its own form, top panel) and <strong>Save Square settings</strong> (credentials, device ID, toggles).</p>
    <ul>
        <li><strong>Enable Square payments in ARK</strong> — master switch after credentials are saved.</li>
        <li><strong>Terminal device ID</strong> — ARK auto-fills when pairing completes (status poll or webhook). Manual paste from Square Dashboard → Devices still works. Never use the sticker serial.</li>
        <li><strong>Counter terminal</strong> — push checkout to the paired reader from the RO financial rail.</li>
        <li><strong>Counter keyed entry</strong> — browser card form when the reader is unavailable or still in Register mode.</li>
        <li><strong>Customer portal pay</strong> — pay balance on issued invoice (portal).</li>
        <li><strong>Emailed invoice pay link</strong> — “Pay now” in invoice email when balance &gt; 0.</li>
    </ul>
    <p>Manual <strong>Record Payment</strong> (cash, check, external swipe) remains — do not remove that habit for non-Square tenders.</p>

    <h3>Step 4 — Put the Square Terminal in Connected mode</h3>
    <p>Do this <strong>before</strong> terminal capture works on repair orders. Square’s authoritative walkthrough: <a href="https://developer.squareup.com/docs/terminal-api/integrate-square-terminal" target="_blank" rel="noopener">Connect a Square Terminal to a POS application</a>.</p>

    <p><strong>Square rule:</strong> Terminal API only accepts device codes from the <code>CreateDeviceCode</code> API (<code>product_type</code> = <code>TERMINAL_API</code>). Codes created elsewhere in the Square Dashboard are <strong>not</strong> compatible.</p>

    <h4>1 — Generate the code in ARK first</h4>
    <ol>
        <li><a href="{{ $paymentsSettings }}">Settings → Shop → Payments</a> → save production credentials and Location ID.</li>
        <li>In the <strong>Terminal pairing</strong> panel, click <strong>Generate pairing code</strong> — not <strong>Save Square settings</strong>.</li>
        <li>ARK shows a large short code (e.g. six letters). Codes expire in <strong>5 minutes</strong>; click Generate again if it expires.</li>
        <li>Keep this screen open — ARK polls pairing status and auto-fills Terminal device ID when the reader connects.</li>
    </ol>

    <h4>2 — Prepare the reader (sign out or full reset)</h4>
    <p>Start with sign out. If the reader will not offer <strong>Device Code</strong> sign-in, do a <strong>full reset</strong> and run through Square’s initial setup again — that was required in production when the reader had been running Square Register.</p>
    <ol>
        <li><strong>Try sign out first</strong> — swipe from the left edge → <strong>Settings</strong> → <strong>Sign Out</strong> (some firmware: <strong>Switch mode</strong>).</li>
        <li><strong>If you only see email/password login</strong> — perform a <strong>full factory reset</strong> on the Square Terminal (Square’s device reset / re-setup flow), connect Wi‑Fi, update firmware if prompted, and stop at the <strong>Sign in</strong> screen. Do <strong>not</strong> sign in with merchant email and password.</li>
        <li>On the <strong>Sign in</strong> screen, tap <strong>Device Code</strong> — this is the Terminal API path ARK needs.</li>
        <li>Enter the code from ARK within 5 minutes.</li>
        <li>Success = <strong>idle / ready waiting screen</strong> (minimal/dark), not the item library or register UI.</li>
    </ol>

    <h4>3 — Finish in ARK</h4>
    <ol>
        <li>When status shows <strong>PAIRED</strong>, Terminal device ID should appear in Settings (auto-saved). Click <strong>Save Square settings</strong> if you changed toggles.</li>
        <li>Enable <strong>Counter terminal</strong>. Confirm Location ID matches the reader’s Square location.</li>
        <li>Fallback device ID sources: Square Dashboard → <strong>Devices</strong>, or <code>device.code.paired</code> webhook payload.</li>
    </ol>

    <p><strong>Fallback code source:</strong> Square Developer Dashboard → API Explorer → <code>CreateDeviceCode</code> with <code>TERMINAL_API</code> — only if ARK Generate is unavailable. Dashboard-created codes from other screens are <strong>not</strong> compatible.</p>

    <p class="text-sm text-slate-600"><strong>Sandbox:</strong> Square Sandbox does not support the Devices API for real reader pairing. Use Square’s documented sandbox test <code>device_id</code> values for terminal API testing, or test keyed entry against sandbox credentials.</p>

    <h4>Switch back to Square Register (normal counter sales)</h4>
    <p>Sign out on the reader and sign in with your regular Square merchant credentials (or use Switch mode back to Register). The reader will no longer accept ARK checkouts until you pair again with a Terminal API device code.</p>

    <h4>Test terminal capture</h4>
    <ol>
        <li>Open a RO with <strong>issued invoice</strong> and <strong>balance due</strong>.</li>
        <li>Financial rail → <strong>Charge card</strong> (terminal).</li>
        <li>Reader should prompt for the card. ARK shows a waiting state until payment completes or you cancel.</li>
    </ol>

    <h3>Capture surfaces at a glance</h3>
    <dl>
        <dt>Counter — Terminal</dt>
        <dd>Staff on RO financial rail. Requires Connected / Terminal API mode on the reader. Default amount = balance due. ARK polls until the reader completes or staff cancels.</dd>
        <dt>Counter — Keyed</dt>
        <dd>Staff enters card in ARK modal (Square Web Payments SDK). Works without reader pairing — use when the reader is in Register mode or unavailable.</dd>
        <dt>Portal / email pay</dt>
        <dd>Customer pays issued invoice balance via signed link or portal — same server completion path. No physical reader involved.</dd>
    </dl>

    <h3>Verify before you trust it</h3>
    <ol>
        <li>Settings → Payments — credentials detected (green banner).</li>
        <li>Reader is on the idle Connected screen, not the Square register.</li>
        <li>Small live or sandbox charge on a test RO — ledger entry appears; balance due drops.</li>
        <li>Terminal path — waiting state clears when the customer pays on the device.</li>
        <li>Keyed path — works even when the reader is in Register mode (sanity check for credentials).</li>
    </ol>

    <h3>Common owner mistakes</h3>
    <ul>
        <li><strong>Reader left in Square Register / POS mode</strong> — most common. ARK sends a checkout; Square returns “merchant not authorized for device_id”. Fix: Generate pairing code in ARK → full reset reader if Sign Out is not enough → Device Code sign-in → idle Connected screen.</li>
        <li><strong>Sign Out did not show Device Code</strong> — reader was stuck in Register posture. Full factory reset and re-setup, then <strong>Device Code</strong> only (never merchant email on first sign-in after reset).</li>
        <li><strong>Clicked Save instead of Generate pairing code</strong> — the pairing button is its own form on the Payments page. Generate first; Save is for credentials and device ID.</li>
        <li><strong>Signed into reader with email/password</strong> — puts the device in Register mode. Use <strong>Device Code</strong> sign-in for ARK.</li>
        <li><strong>Wrong device code source</strong> — used a code from Square Dashboard instead of <code>CreateDeviceCode</code> with <code>TERMINAL_API</code>. ARK cannot push checkouts with those codes.</li>
        <li><strong>Expired device code</strong> — codes expire after 5 minutes if unused. Request a new code and enter it on the reader immediately.</li>
        <li><strong>Wrong device ID</strong> — pasted physical serial sticker or an ID from a reader still in Register mode. Use the <code>device_id</code> after <code>PAIRED</code> status (Devices, webhook, or <code>GetDeviceCode</code>).</li>
        <li><strong>Wrong location ID</strong> — reader registered at a different Square location than Settings → Payments.</li>
        <li><strong>Different Square accounts</strong> — access token from one merchant, reader paired to another.</li>
        <li><strong>Sandbox vs production mismatch</strong> — sandbox credentials with a live reader, or the reverse.</li>
        <li><strong>Running RO payments through Square POS app</strong> — bypasses ARK ledger unless you manually record payment.</li>
        <li><strong>Webhook missing or wrong URL</strong> — terminal poll may still work; keyed and portal rely more on webhook for reliability.</li>
        <li><strong>Charging before final invoice</strong> — Square capture on the RO requires issued invoice and balance due. Record deposits manually or use keyed/terminal only after invoice issue.</li>
    </ul>

    <h3>When ARK shows a Square terminal error</h3>
    <p>ARK surfaces reader and configuration problems on the RO financial rail instead of silent failure. Typical messages:</p>
    <ul>
        <li><strong>Merchant not authorized for device_id</strong> — reader not in Connected mode, wrong device ID, wrong merchant, or wrong location. Re-pair with a Terminal API device code and refresh the device ID in Settings.</li>
        <li><strong>Square terminal is not configured</strong> — enable Counter terminal and paste device ID in Settings → Payments.</li>
    </ul>
    <p>Until terminal pairing is fixed, use <strong>Keyed card entry</strong> or <strong>Record Payment</strong> so the customer is not blocked.</p>

    <h3>Money authority (do not break this)</h3>
    <p>ARK never trusts the browser for totals. Balance due, tax, and invoice snapshots stay server-authoritative. Square only captures what ARK initiates.</p>

    <p>Shop phone and SMS: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Communications setup</a>.</p>
</div>
