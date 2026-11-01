@php
    $qz = $qzPrintingReference ?? [];
    $health = $qz['health'] ?? [];
    $ready = (bool) ($health['ready'] ?? false);
    $selfTest = (bool) ($qz['self_test'] ?? false);
@endphp

<div data-settings-qz-certificates>
    <div class="mb-3">
        <div class="subheader">Certificate &amp; silent printing</div>
        <h3 class="mb-1">ARK-owned QZ Tray setup</h3>
        <p class="text-secondary small mb-0">Stock QZ Tray from <a href="https://qz.io/download/" target="_blank" rel="noopener">qz.io</a> - no fork. Server signs print requests; each workstation trusts ARK Root CA via <code>override.crt</code>.</p>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Signing ready</div>
                    <div @class(['h3 mb-0', 'text-success' => $ready, 'text-danger' => ! $ready])>{{ $ready ? 'Yes' : 'No' }}</div>
                    <div class="small text-secondary">PEM paths readable and valid</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Self-test</div>
                    <div @class(['h3 mb-0', 'text-success' => $selfTest, 'text-danger' => ! $selfTest])>{{ $selfTest ? 'Passed' : 'Failed' }}</div>
                    <div class="small text-secondary">SHA512 sign + verify round-trip</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Algorithm</div>
                    <div class="h3 mb-0">{{ $qz['algorithm'] ?? 'SHA512' }}</div>
                    <div class="small text-secondary">QZ 2.1+ default</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Health check</div>
                    <div class="h3 mb-0"><a href="{{ $qz['sign_health_url'] ?? '#' }}" target="_blank" rel="noopener">GET sign-health</a></div>
                    <div class="small text-secondary">Settings manage permission</div>
                </div>
            </div>
        </div>
    </div>

    @if (! $ready)
        <div class="alert alert-warning" role="status">
            <div class="fw-medium">Signing not ready on this server.</div>
            @if (filled($health['certificate_error_hint'] ?? null))
                <div class="mt-1">Certificate: {{ $health['certificate_error_hint'] }}</div>
            @endif
            @if (filled($health['private_key_error_hint'] ?? null))
                <div class="mt-1">Private key: {{ $health['private_key_error_hint'] }}</div>
            @endif
            @if (blank($health['certificate_error_hint'] ?? null) && blank($health['private_key_error_hint'] ?? null))
                <div class="mt-1">Set <code>QZ_CERTIFICATE_PATH</code> and <code>QZ_PRIVATE_KEY_PATH</code> in server <code>.env</code> (outside <code>public/</code>).</div>
            @endif
        </div>
    @endif

    <dl class="row small border rounded mx-0 py-2 mb-3">
        <dt class="col-12 col-sm-3 text-secondary">Cert path</dt>
        <dd class="col-12 col-sm-9 font-monospace text-break">{{ filled($qz['certificate_path'] ?? '') ? $qz['certificate_path'] : '(not set)' }}</dd>
        <dt class="col-12 col-sm-3 text-secondary">Key path</dt>
        <dd class="col-12 col-sm-9 font-monospace text-break mb-0">{{ filled($qz['private_key_path'] ?? '') ? $qz['private_key_path'] : '(not set)' }}</dd>
    </dl>

    <div class="card card-sm">
        <div class="card-header">
            <h4 class="card-title">Runbook</h4>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="fw-medium">1. Local machine - generate dev certificates (repo root)</div>
                <div class="small text-secondary mb-1">Do not commit output. Root key stays offline.</div>
                <pre class="mb-0">bash infra/qz-dev/generate-ark-printing-certs.sh
bash infra/qz-dev/verify-ark-printing-certs.sh</pre>
            </div>

            <div class="mb-3">
                <div class="fw-medium">2. Server - point <code>.env</code> at the signing PEMs</div>
                <div class="small text-secondary mb-1">Development paths below. Production: store under VPS <code>shared/qz/</code> outside <code>public/</code>, chmod 600 key / 644 cert.</div>
                <pre class="mb-1">QZ_CERTIFICATE_PATH=infra/qz-dev/certs/digital-certificate.txt
QZ_PRIVATE_KEY_PATH=infra/qz-dev/certs/private-key.pem
QZ_SIGNATURE_ALGORITHM=sha512</pre>
                <div class="small text-secondary mb-1">Production example:</div>
                <pre class="mb-0">QZ_CERTIFICATE_PATH=/var/www/sites/autorepairkeeper/production/shared/qz/digital-certificate.txt
QZ_PRIVATE_KEY_PATH=/var/www/sites/autorepairkeeper/production/shared/qz/private-key.pem
QZ_SIGNATURE_ALGORITHM=sha512</pre>
            </div>

            <div class="mb-3">
                <div class="fw-medium mb-1">3. Server - verify signing (SSH on VPS or local Herd)</div>
                <pre class="mb-0">php artisan tinker --execute="dump(App\Ark\Operations\Printing\QzTraySigning::healthSnapshot());"
php artisan tinker --execute="dump(App\Ark\Operations\Printing\QzTraySigning::selfTestSigningRoundTrip());"</pre>
            </div>

            <div class="mb-3">
                <div class="fw-medium">4. Each print workstation - trust ARK Root in stock QZ Tray</div>
                <div class="small text-secondary mb-1">Copy <code>infra/qz-dev/certs/override.crt</code> (or production ARK root), restart QZ Tray.</div>
                <pre class="mb-0"># Windows
copy override.crt "C:\Program Files\QZ Tray\override.crt"

# macOS
cp override.crt "/Applications/QZ Tray.app/Contents/Resources/override.crt"

# Linux (or set authcert.override in qz-tray.properties)
cp override.crt /opt/qz-tray/override.crt</pre>
            </div>

            <div>
                <div class="fw-medium">5. Verify end-to-end</div>
                <div class="small text-secondary">Open <a href="{{ $qz['sign_health_url'] ?? '#' }}" target="_blank" rel="noopener">sign-health</a> → <code>status: ok</code>. Print a key tag from an RO - no Allow prompt.
                    @if (filled($qz['poc_url'] ?? null))
                        Local POC: <a href="{{ $qz['poc_url'] }}" target="_blank" rel="noopener">{{ $qz['poc_url'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <p class="small text-secondary mt-3 mb-0">Full feasibility notes: <code>docs/printing/ark-self-signed-feasibility.md</code> in the repo.</p>
</div>
