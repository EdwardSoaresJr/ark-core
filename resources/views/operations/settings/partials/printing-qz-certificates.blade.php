@php
    $qz = $qzPrintingReference ?? [];
    $health = $qz['health'] ?? [];
    $ready = (bool) ($health['ready'] ?? false);
    $selfTest = (bool) ($qz['self_test'] ?? false);
@endphp

<div class="space-y-3">
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Certificate &amp; silent printing</p>
        <h3 class="text-sm font-black text-slate-950">ARK-owned QZ Tray setup</h3>
        <p class="mt-0.5 text-xs leading-5 text-slate-500">Stock QZ Tray from <a href="https://qz.io/download/" class="underline decoration-slate-300 hover:text-slate-900" target="_blank" rel="noopener">qz.io</a> — no fork. Server signs print requests; each workstation trusts ARK Root CA via <code class="text-[11px]">override.crt</code>.</p>
    </div>

    <div class="grid gap-px border border-slate-300 bg-slate-300 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Signing ready</p>
            <p class="mt-0.5 text-sm font-black {{ $ready ? 'text-emerald-800' : 'text-rose-800' }}">{{ $ready ? 'Yes' : 'No' }}</p>
            <p class="text-[11px] text-slate-500">PEM paths readable and valid</p>
        </div>
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Self-test</p>
            <p class="mt-0.5 text-sm font-black {{ $selfTest ? 'text-emerald-800' : 'text-rose-800' }}">{{ $selfTest ? 'Passed' : 'Failed' }}</p>
            <p class="text-[11px] text-slate-500">SHA512 sign + verify round-trip</p>
        </div>
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Algorithm</p>
            <p class="mt-0.5 text-sm font-black text-slate-950">{{ $qz['algorithm'] ?? 'SHA512' }}</p>
            <p class="text-[11px] text-slate-500">QZ 2.1+ default</p>
        </div>
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Health check</p>
            <p class="mt-0.5 text-sm font-black text-slate-950">
                <a href="{{ $qz['sign_health_url'] ?? '#' }}" class="underline decoration-slate-300 hover:text-slate-700" target="_blank" rel="noopener">GET sign-health</a>
            </p>
            <p class="text-[11px] text-slate-500">Settings manage permission</p>
        </div>
    </div>

    @if (! $ready)
        <div class="border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
            <p class="font-semibold">Signing not ready on this server.</p>
            @if (filled($health['certificate_error_hint'] ?? null))
                <p class="mt-1">Certificate: {{ $health['certificate_error_hint'] }}</p>
            @endif
            @if (filled($health['private_key_error_hint'] ?? null))
                <p class="mt-1">Private key: {{ $health['private_key_error_hint'] }}</p>
            @endif
            @if (blank($health['certificate_error_hint'] ?? null) && blank($health['private_key_error_hint'] ?? null))
                <p class="mt-1">Set <code>QZ_CERTIFICATE_PATH</code> and <code>QZ_PRIVATE_KEY_PATH</code> in server <code>.env</code> (outside <code>public/</code>).</p>
            @endif
        </div>
    @endif

    <dl class="grid gap-2 rounded-md border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs sm:grid-cols-[9rem_minmax(0,1fr)]">
        <dt class="font-semibold text-slate-500">Cert path</dt>
        <dd class="break-all font-mono text-[11px] text-slate-800">{{ filled($qz['certificate_path'] ?? '') ? $qz['certificate_path'] : '(not set)' }}</dd>
        <dt class="font-semibold text-slate-500">Key path</dt>
        <dd class="break-all font-mono text-[11px] text-slate-800">{{ filled($qz['private_key_path'] ?? '') ? $qz['private_key_path'] : '(not set)' }}</dd>
    </dl>

    <div class="space-y-3 border border-slate-200">
        <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">Runbook</p>

        <div class="space-y-3 px-3 pb-3">
            <div>
                <p class="text-xs font-semibold text-slate-800">1. Local machine — generate dev certificates (repo root)</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Do not commit output. Root key stays offline.</p>
                <pre class="ops-printing-cmd">bash infra/qz-dev/generate-ark-printing-certs.sh
bash infra/qz-dev/verify-ark-printing-certs.sh</pre>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-800">2. Server — point <code class="text-[11px]">.env</code> at the signing PEMs</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Development paths below. Production: store under VPS <code class="text-[11px]">shared/qz/</code> outside <code class="text-[11px]">public/</code>, chmod 600 key / 644 cert.</p>
                <pre class="ops-printing-cmd">QZ_CERTIFICATE_PATH=infra/qz-dev/certs/digital-certificate.txt
QZ_PRIVATE_KEY_PATH=infra/qz-dev/certs/private-key.pem
QZ_SIGNATURE_ALGORITHM=sha512</pre>
                <p class="mt-1 text-[11px] text-slate-500">Production example:</p>
                <pre class="ops-printing-cmd">QZ_CERTIFICATE_PATH=/var/www/sites/autorepairkeeper/production/shared/qz/digital-certificate.txt
QZ_PRIVATE_KEY_PATH=/var/www/sites/autorepairkeeper/production/shared/qz/private-key.pem
QZ_SIGNATURE_ALGORITHM=sha512</pre>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-800">3. Server — verify signing (SSH on VPS or local Herd)</p>
                <pre class="ops-printing-cmd">php artisan tinker --execute="dump(App\Ark\Operations\Printing\QzTraySigning::healthSnapshot());"
php artisan tinker --execute="dump(App\Ark\Operations\Printing\QzTraySigning::selfTestSigningRoundTrip());"</pre>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-800">4. Each print workstation — trust ARK Root in stock QZ Tray</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Copy <code class="text-[11px]">infra/qz-dev/certs/override.crt</code> (or production ARK root), restart QZ Tray.</p>
                <pre class="ops-printing-cmd"># Windows
copy override.crt "C:\Program Files\QZ Tray\override.crt"

# macOS
cp override.crt "/Applications/QZ Tray.app/Contents/Resources/override.crt"

# Linux (or set authcert.override in qz-tray.properties)
cp override.crt /opt/qz-tray/override.crt</pre>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-800">5. Verify end-to-end</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Open <a href="{{ $qz['sign_health_url'] ?? '#' }}" class="underline decoration-slate-300 hover:text-slate-800" target="_blank" rel="noopener">sign-health</a> → <code class="text-[11px]">status: ok</code>. Print a key tag from an RO — no Allow prompt.
                    @if (filled($qz['poc_url'] ?? null))
                        Local POC: <a href="{{ $qz['poc_url'] }}" class="underline decoration-slate-300 hover:text-slate-800" target="_blank" rel="noopener">{{ $qz['poc_url'] }}</a>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <p class="text-[11px] leading-4 text-slate-400">Full feasibility notes: <code class="text-[11px]">docs/printing/ark-self-signed-feasibility.md</code> in the repo.</p>
</div>
