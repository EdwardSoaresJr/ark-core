<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ARK QZ POC (local only)</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 1.5rem; max-width: 52rem; color: #0f172a; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { margin: 0 0 0.75rem; color: #475569; font-size: 0.9rem; }
        button { margin-right: 0.5rem; margin-bottom: 0.5rem; padding: 0.45rem 0.75rem; font-weight: 600; cursor: pointer; }
        pre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.75rem; overflow: auto; font-size: 0.8rem; white-space: pre-wrap; }
        .ok { color: #166534; }
        .bad { color: #b91c1c; }
        dl { display: grid; grid-template-columns: 10rem 1fr; gap: 0.25rem 0.75rem; font-size: 0.85rem; margin: 0 0 1rem; }
        dt { color: #64748b; }
    </style>
</head>
<body>
    <h1>ARK × Stock QZ Tray — signing POC</h1>
    <p>Local environment only. Proves browser → ARK <code>sign-message</code> → stock QZ Tray with ARK-owned dev certificates. Install <code>infra/qz-dev/certs/override.crt</code> into QZ Tray before connecting.</p>

    <dl>
        <dt>Signing ready</dt>
        <dd class="{{ ($health['ready'] ?? false) ? 'ok' : 'bad' }}">{{ ($health['ready'] ?? false) ? 'yes' : 'no' }}</dd>
        <dt>Algorithm</dt>
        <dd>{{ $algorithm }}</dd>
        <dt>Cert path</dt>
        <dd><code>{{ config('printing.qz.certificate_path') ?: '(unset)' }}</code></dd>
        <dt>Key path</dt>
        <dd><code>{{ config('printing.qz.private_key_path') ?: '(unset)' }}</code></dd>
        <dt>Self-test</dt>
        <dd class="{{ $selfTest ? 'ok' : 'bad' }}">{{ $selfTest ? 'passed' : 'failed' }}</dd>
    </dl>

    <button type="button" id="btn-connect">Connect QZ</button>
    <button type="button" id="btn-version">Signed getVersion</button>
    <button type="button" id="btn-clear">Clear log</button>

    <pre id="log">Waiting…</pre>

    <script src="{{ asset('vendor/qz/qz-tray.js') }}"></script>
    <script>
        const signUrl = @json(route('operations.printing.qz.sign'));
        const serverCert = @json($certificate);
        const signAlgo = @json($algorithm);
        const logEl = document.getElementById('log');

        function log(line) {
            const ts = new Date().toISOString().slice(11, 19);
            logEl.textContent += `\n[${ts}] ${line}`;
            logEl.scrollTop = logEl.scrollHeight;
        }

        document.getElementById('btn-clear').addEventListener('click', () => {
            logEl.textContent = 'Cleared.';
        });

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        function initSecurity() {
            if (!serverCert || serverCert.indexOf('BEGIN CERTIFICATE') === -1) {
                throw new Error('Server certificate PEM missing. Run generate-ark-printing-certs.sh and set QZ_* in .env');
            }

            qz.security.setCertificatePromise((resolve) => resolve(serverCert));
            if (typeof qz.security.setSignatureAlgorithm === 'function') {
                qz.security.setSignatureAlgorithm(signAlgo);
            }
            qz.security.setSignaturePromise((toSign) => (resolve, reject) => {
                fetch(signUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ data: toSign }),
                })
                    .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
                    .then(({ ok, body }) => {
                        if (!ok || !body.signature) {
                            reject(new Error(body.message || body.error || 'sign-message failed'));
                            return;
                        }
                        log('sign-message OK (' + (body.signature?.length || 0) + ' chars)');
                        resolve(body.signature);
                    })
                    .catch(reject);
            });
            log('QZ security promises attached (server cert + sign-message).');
        }

        document.getElementById('btn-connect').addEventListener('click', async () => {
            try {
                initSecurity();
                await qz.websocket.connect();
                log('qz.websocket.connect() OK — stock QZ accepted ARK certificate chain.');
            } catch (e) {
                log('CONNECT FAILED: ' + (e?.message || e));
            }
        });

        document.getElementById('btn-version').addEventListener('click', async () => {
            try {
                if (!qz.websocket.isActive()) {
                    await qz.websocket.connect();
                }
                const version = await qz.api.getVersion();
                log('Signed getVersion OK: ' + version);
            } catch (e) {
                log('getVersion FAILED: ' + (e?.message || e));
            }
        });
    </script>
</body>
</html>
