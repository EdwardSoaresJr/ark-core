@php
    /** @var array<string, string>|null $trustRoot */
    $fingerprint = $trustRoot ? implode(':', str_split(strtoupper($trustRoot['sha256']), 2)) : null;
    $macCommand = $macosCommandUrl ? "curl -fsSL '".$macosCommandUrl."' -o /tmp/ark-print-setup.sh && sudo bash /tmp/ark-print-setup.sh" : null;
@endphp

<x-operations.app title="Set Up Printing" :printing="true" ui="tabler">
    <x-tabler.page layout="focused" title="Set Up Printing" subtitle="Silent label printing through QZ Tray on this computer">
        <div data-printing-setup>
            @unless ($signingReady)
                <div class="alert alert-danger" role="alert" data-printing-setup-server>
                    <div class="fw-bold">This shop's printing certificate is not ready on the ARK server.</div>
                    <div>Printing will keep asking to Allow until it is. This is not a problem with this computer; {{ $canManagePrinting ? 'contact ARK support.' : 'tell your manager or ARK support.' }}</div>
                </div>
            @elseif ($certificateExpiresSoon && $certificate)
                <div class="alert alert-warning" role="status">The printing certificate expires on {{ $certificate['not_after'] }}. Contact ARK support for a new one.</div>
            @endif

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title">1. Install QZ Tray</h2></div>
                <div class="card-body">
                    <p class="mb-2">ARK prints labels through QZ Tray, a free program from QZ Industries. If this computer does not have it yet, install it from QZ's official download page and start it once.</p>
                    <a class="btn btn-outline-primary" href="https://qz.io/download/" target="_blank" rel="noopener">Open QZ Tray download page</a>
                    <div class="form-hint mt-2">Version 2.2.5 or newer is recommended (2.1.6 at least).</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title">2. Run ARK Print Setup on this computer</h2></div>
                <div class="card-body">
                    @if ($setupAvailable)
                        <p class="mb-3">It lets QZ Tray trust this shop, so printing from ARK no longer asks to Allow. It changes only ARK's own entry in QZ Tray's settings and keeps a backup. Running it again is safe.</p>
                        <div class="row g-3">
                            <div class="col-12 col-lg-6" data-setup-platform="windows">
                                <div class="card card-sm h-100">
                                    <div class="card-body">
                                        <h3 class="card-title mb-1">Windows</h3>
                                        <ol class="small ps-3 mb-3" style="list-style: decimal">
                                            <li>Download and open the file.</li>
                                            <li>If Windows shows "Windows protected your PC", choose More info, then Run anyway.</li>
                                            <li>Choose Yes when Windows asks for permission.</li>
                                        </ol>
                                        <a class="btn btn-primary" href="{{ $windowsDownloadUrl }}" data-download-windows>Download for Windows</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6" data-setup-platform="macos">
                                <div class="card card-sm h-100">
                                    <div class="card-body">
                                        <h3 class="card-title mb-1">Mac</h3>
                                        <ol class="small ps-3 mb-2" style="list-style: decimal">
                                            <li>Open Terminal (Applications → Utilities).</li>
                                            <li>Paste this command, press Return, and type this Mac's password.</li>
                                        </ol>
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control font-monospace small" readonly value="{{ $macCommand }}" aria-label="Mac setup command" data-mac-command>
                                            <button type="button" class="btn btn-primary" data-copy-mac-command>Copy</button>
                                        </div>
                                        <div class="form-hint">The command works for 30 minutes; reload this page for a new one. <a href="{{ $macosDownloadUrl }}">Download the file instead</a> (then run <code>sudo bash</code> on it).</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="small text-secondary mt-3">
                            Setup version {{ $setupVersion }}. It trusts “{{ $trustRoot['common_name'] }} / {{ $trustRoot['organization'] }}”, valid until {{ $trustRoot['not_after'] }}.<br>
                            SHA-256 fingerprint: <code class="text-break">{{ $fingerprint }}</code>. The setup checks this before changing anything.
                        </div>
                    @else
                        <p class="text-secondary mb-0">The setup download becomes available once this shop's printing certificate is ready on the ARK server.</p>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title">3. Check the connection</h2></div>
                <div class="card-body">
                    <button type="button" class="btn btn-primary" data-check-connection>Check connection</button>
                    <div class="mt-3" data-connection-result role="status" aria-live="polite"></div>
                </div>
            </div>

            <div class="card mb-3" data-printer-step hidden>
                <div class="card-header"><h2 class="card-title">4. Choose a printer and print a test label</h2></div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="printing-setup-printer">Printer on this computer</label>
                            <select id="printing-setup-printer" class="form-select" data-printer-select></select>
                        </div>
                        <div class="col-12 col-md-5">
                            <button type="button" class="btn btn-outline-primary w-100" data-print-test>Print a test label</button>
                        </div>
                    </div>
                    <div class="form-hint mt-2">The test label shows “ARK printing test” and the shop name. It is not a customer document.</div>
                    <div class="mt-3" data-print-result role="status" aria-live="polite"></div>

                    <hr>
                    <div class="small mb-2">
                        Key tag printer for this shop: <strong data-current-key-tag>{{ $keyTagPrinter !== '' ? $keyTagPrinter : 'Brother QL-800 (default)' }}</strong>.
                        Oil sticker printer: <strong data-current-oil-sticker>{{ $oilStickerPrinter !== '' ? $oilStickerPrinter : 'same as key tags' }}</strong>.
                    </div>
                    @if ($canManagePrinting)
                        <div class="btn-list">
                            <button type="button" class="btn btn-outline-secondary" data-save-printer="qz_printing_key_tag_printer">Use for key tags</button>
                            <button type="button" class="btn btn-outline-secondary" data-save-printer="qz_printing_oil_sticker_printer">Use for oil stickers</button>
                            <a class="btn btn-link" href="{{ $printingSettingsUrl }}">Label sizes and other printing settings</a>
                        </div>
                        <div class="mt-2" data-save-result role="status" aria-live="polite"></div>
                    @else
                        <div class="small text-secondary">A manager can change which printer ARK uses in Settings → Label Printing.</div>
                    @endif
                </div>
            </div>
        </div>
    </x-tabler.page>

    <script>
    (function () {
        var root = document.querySelector('[data-printing-setup]');
        if (!root) { return; }
        var signingReady = @json($signingReady);
        var testLabelUrl = @json($testLabelUrl);
        var savePrintersUrl = @json($savePrintersUrl);
        var keyTagPrinter = @json($keyTagPrinter);

        var ua = navigator.userAgent || '';
        var preferred = /Windows/i.test(ua) ? 'windows' : (/Macintosh|Mac OS X/i.test(ua) ? 'macos' : null);
        if (preferred) {
            var card = root.querySelector('[data-setup-platform="' + preferred + '"]');
            if (card && card.parentNode) { card.parentNode.insertBefore(card, card.parentNode.firstChild); }
        }

        var copyBtn = root.querySelector('[data-copy-mac-command]');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var input = root.querySelector('[data-mac-command]');
                input.select();
                (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject())
                    .then(function () { copyBtn.textContent = 'Copied'; })
                    .catch(function () { document.execCommand('copy'); copyBtn.textContent = 'Copied'; });
            });
        }

        function show(el, kind, html) {
            el.innerHTML = '<div class="alert alert-' + kind + ' mb-0">' + html + '</div>';
        }
        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
        }
        function versionAtLeast(v, min) {
            var a = String(v || '').split(/[.-]/).map(Number), b = min.split('.').map(Number);
            for (var i = 0; i < b.length; i++) {
                if ((a[i] || 0) > b[i]) { return true; }
                if ((a[i] || 0) < b[i]) { return false; }
            }
            return true;
        }

        var connectionResult = root.querySelector('[data-connection-result]');
        var printerStep = root.querySelector('[data-printer-step]');
        var printerSelect = root.querySelector('[data-printer-select]');
        var signingFailed = false;
        window.addEventListener('qz-signing-failed', function () { signingFailed = true; });

        root.querySelector('[data-check-connection]').addEventListener('click', async function (event) {
            var btn = event.currentTarget;
            btn.disabled = true;
            signingFailed = false;
            show(connectionResult, 'info', 'Connecting to QZ Tray… If QZ Tray asks to Allow, this computer is not set up yet: choose Block, then run step 2.');
            try {
                await arkTimeout(ensureQZConnection(2, 400), 20000);
            } catch (e) {
                show(connectionResult, 'danger', '<strong>QZ Tray is not running on this computer.</strong> Install it (step 1) or start it, then check again.');
                btn.disabled = false;
                return;
            }
            var version = '';
            try { version = await arkTimeout(qz.api.getVersion(), 10000); } catch (e) {}
            try {
                var printers = await arkTimeout(qz.printers.find(), 30000);
                printerSelect.innerHTML = '';
                (printers || []).forEach(function (name) {
                    var option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    if (name === keyTagPrinter) { option.selected = true; }
                    printerSelect.appendChild(option);
                });
                var note = versionAtLeast(version, '2.1.6') ? '' : ' This QZ Tray version is old; update it from qz.io.';
                if (!signingReady) {
                    show(connectionResult, 'warning', 'Connected to QZ Tray ' + escapeHtml(version) + ', but this shop\'s server certificate is not ready, so printing will ask to Allow.');
                } else {
                    show(connectionResult, 'success', '<strong>Connected to QZ Tray ' + escapeHtml(version) + '.</strong> ' + (printers || []).length + ' printer(s) found.' + escapeHtml(note));
                }
                printerStep.hidden = (printers || []).length === 0;
                if ((printers || []).length === 0) {
                    show(connectionResult, 'warning', 'Connected to QZ Tray ' + escapeHtml(version) + ', but this computer has no printers. Add the label printer in the system settings, then check again.');
                }
            } catch (e) {
                var message = String(e && e.message || e);
                if (signingFailed || /SIGNING_FAILED/.test(message)) {
                    show(connectionResult, 'danger', '<strong>The ARK server could not sign the request.</strong> This is not a problem with this computer; contact ARK support.');
                } else if (/timeout/i.test(message)) {
                    show(connectionResult, 'warning', '<strong>QZ Tray did not answer.</strong> If it is showing an Allow prompt, this computer is not set up yet: choose Block, run ARK Print Setup (step 2), then check again.');
                } else if (/blocked|untrusted|invalid certificate|bad signature/i.test(message)) {
                    show(connectionResult, 'danger', '<strong>QZ Tray on this computer does not trust this shop yet.</strong> Run ARK Print Setup (step 2), then check again.');
                } else {
                    show(connectionResult, 'danger', 'QZ Tray answered with an error: ' + escapeHtml(message));
                }
            } finally {
                btn.disabled = false;
            }
        });

        var printResult = root.querySelector('[data-print-result]');
        root.querySelector('[data-print-test]').addEventListener('click', async function (event) {
            var printer = printerSelect.value;
            if (!printer) { return; }
            show(printResult, 'info', 'Printing a test label on ' + escapeHtml(printer) + '…');
            try {
                await arkPrintPDF(printer, testLabelUrl, event.currentTarget, {
                    document: 'print_test_key_tag',
                    printSource: 'test',
                    throwOnError: true,
                    skipSuccessToast: true,
                });
                if (printer === keyTagPrinter && typeof arkQzWizardMarkVerified === 'function') {
                    arkQzWizardMarkVerified(printer);
                }
                show(printResult, 'success', 'Sent to ' + escapeHtml(printer) + '. If no Allow prompt appeared, this computer is set up.');
            } catch (e) {
                show(printResult, 'danger', 'The test label did not print: ' + escapeHtml(e && e.message || e));
            }
        });

        root.querySelectorAll('[data-save-printer]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var printer = printerSelect.value;
                var saveResult = root.querySelector('[data-save-result]');
                if (!printer) { return; }
                var body = {};
                body[btn.getAttribute('data-save-printer')] = printer;
                var response = await fetch(savePrintersUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': arkPrintCsrfToken() },
                    body: JSON.stringify(body),
                });
                if (!response.ok) {
                    show(saveResult, 'danger', 'The printer was not saved.');
                    return;
                }
                var saved = await response.json();
                keyTagPrinter = saved.key_tag_printer;
                window.ARK_PRINTERS = Object.assign(window.ARK_PRINTERS || {}, { keyTag: saved.key_tag_printer, oilSticker: saved.oil_sticker_printer, default: saved.key_tag_printer });
                root.querySelector('[data-current-key-tag]').textContent = saved.key_tag_printer;
                root.querySelector('[data-current-oil-sticker]').textContent = saved.oil_sticker_printer;
                show(saveResult, 'success', 'Saved. ARK uses ' + escapeHtml(printer) + ' for this shop.');
            });
        });
    })();
    </script>
</x-operations.app>
