@php
    $qz = $qzPrintingReference ?? [];
    $health = $qz['health'] ?? [];
    $ready = (bool) ($health['ready'] ?? false);
    $selfTest = (bool) ($qz['self_test'] ?? false);
    $certificate = $health['certificate'] ?? null;
    $root = $health['trust_root'] ?? null;
    $source = $health['source'] ?? 'none';
@endphp

<div data-settings-qz-certificates>
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="subheader">Certificate &amp; silent printing</div>
            <h3 class="mb-1">ARK-owned QZ Tray signing</h3>
            <p class="text-secondary small mb-0">The ARK server signs each print request with this shop's own certificate. Each print computer trusts it once, through ARK Print Setup; after that, printing needs no Allow prompt.</p>
        </div>
        <a class="btn btn-primary" href="{{ $qz['setup_url'] ?? '#' }}">Set Up Printing</a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Signing ready</div>
                    <div @class(['h3 mb-0', 'text-success' => $ready, 'text-danger' => ! $ready])>{{ $ready ? 'Yes' : 'No' }}</div>
                    <div class="small text-secondary">{{ $source === 'shop' ? "This shop's own certificate" : ($source === 'configured' ? 'Server-configured certificate' : 'No certificate yet') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Self-test</div>
                    <div @class(['h3 mb-0', 'text-success' => $selfTest, 'text-danger' => ! $selfTest])>{{ $selfTest ? 'Passed' : 'Failed' }}</div>
                    <div class="small text-secondary">{{ $qz['algorithm'] ?? 'SHA512' }} sign + verify; key matches certificate</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Certificate valid until</div>
                    <div @class(['h3 mb-0', 'text-warning' => (bool) ($health['certificate_expires_soon'] ?? false)])>{{ $certificate['not_after'] ?? '—' }}</div>
                    <div class="small text-secondary">{{ $certificate ? $certificate['common_name'].' / '.$certificate['organization'] : 'Not available' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Workstation trust root</div>
                    <div @class(['h3 mb-0', 'text-success' => (bool) ($health['chains_to_trust_root'] ?? false), 'text-danger' => ! ($health['chains_to_trust_root'] ?? false)])>{{ ($health['chains_to_trust_root'] ?? false) ? 'Ready' : 'Missing' }}</div>
                    <div class="small text-secondary">Needed for ARK Print Setup</div>
                </div>
            </div>
        </div>
    </div>

    @foreach (['certificate_error_hint' => 'Certificate', 'private_key_error_hint' => 'Private key', 'trust_root_error_hint' => 'Trust root'] as $hintKey => $hintLabel)
        @if (filled($health[$hintKey] ?? null))
            <div class="alert alert-warning mb-2" role="status">{{ $hintLabel }}: {{ $health[$hintKey] }}</div>
        @endif
    @endforeach
    @if (! $ready && blank($health['certificate_error_hint'] ?? null) && blank($health['private_key_error_hint'] ?? null))
        <div class="alert alert-warning mb-2" role="status">This shop has no printing certificate on the server yet. ARK support creates it with <code>php artisan ark:printing:qz-certificate --generate</code> for this shop.</div>
    @endif
    @if (($health['private_key_readable'] ?? false) && ! ($health['private_key_permissions_ok'] ?? true))
        <div class="alert alert-warning mb-2" role="status">The signing key file can be read by other accounts on the server. Set it to mode 600.</div>
    @endif

    @if ($root)
        <dl class="row small border rounded mx-0 py-2 mb-3">
            <dt class="col-12 col-sm-3 text-secondary">Trust root</dt>
            <dd class="col-12 col-sm-9">{{ $root['common_name'] }} / {{ $root['organization'] }}, until {{ $root['not_after'] }}</dd>
            <dt class="col-12 col-sm-3 text-secondary">SHA-256</dt>
            <dd class="col-12 col-sm-9 font-monospace text-break mb-0">{{ implode(':', str_split(strtoupper($root['sha256']), 2)) }}</dd>
        </dl>
    @endif

    @if (filled($qz['certificate_path'] ?? ''))
        <dl class="row small border rounded mx-0 py-2 mb-3">
            <dt class="col-12 col-sm-3 text-secondary">Cert path</dt>
            <dd class="col-12 col-sm-9 font-monospace text-break">{{ $qz['certificate_path'] }}</dd>
            <dt class="col-12 col-sm-3 text-secondary">Key path</dt>
            <dd class="col-12 col-sm-9 font-monospace text-break mb-0">{{ $qz['private_key_path'] }}</dd>
        </dl>
    @endif

    <p class="small text-secondary mb-0">
        <a href="{{ $qz['sign_health_url'] ?? '#' }}" target="_blank" rel="noopener">Signing health check</a>
        @if (filled($qz['poc_url'] ?? null))
            · <a href="{{ $qz['poc_url'] }}" target="_blank" rel="noopener">Local QZ proof page</a>
        @endif
        · Operator notes: <code>docs/printing/workstation-setup.md</code>.
    </p>
</div>
