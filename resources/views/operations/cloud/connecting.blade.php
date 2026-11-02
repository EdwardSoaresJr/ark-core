<x-operations.app title="Connecting ARK Platform" ui="tabler">
<x-tabler.page layout="focused" pretitle="ARK Cloud" title="Connecting ARK Platform">
    <div class="card" data-cloud-connecting>
        <div class="card-body p-3">
            <p class="text-secondary">
                Finish signing in on ARK Platform and approve this Box. This page will update when the connection is ready.
                ARK does not sign you into Cloud automatically - Cloud asks for your account as usual.
            </p>

            @if ($pairingCode)
                <div class="card card-sm mb-3">
                    <div class="card-body">
                        <div class="small text-secondary">If you need the pairing code instead:</div>
                        <div class="h3 font-monospace mb-0 mt-1">{{ $pairingCode }}</div>
                        @if ($cloudPairingUrl)
                            <a class="d-inline-block mt-2" href="{{ $cloudPairingUrl }}" target="_blank" rel="noopener">Open pairing page</a>
                        @endif
                    </div>
                </div>
            @endif

            <p id="cloud-connect-status" class="fw-medium mb-0" role="status" aria-live="polite">Waiting for approval…</p>
        </div>

        <div class="card-footer">
            <div class="btn-list">
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="btn">
                    Back to Settings
                </a>
                <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.claim') }}">
                    @csrf
                    @if ($pairingPublicId)
                        <input type="hidden" name="pairing_public_id" value="{{ $pairingPublicId }}">
                    @endif
                    <button type="submit" class="btn btn-primary">
                        Check now
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-tabler.page>

<script>
(function () {
    const statusEl = document.getElementById('cloud-connect-status');
    const pollUrl = @json(route('operations.cloud.poll'));
    let tries = 0;

    async function tick() {
        tries += 1;
        try {
            const res = await fetch(pollUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.connected && data.redirect) {
                statusEl.textContent = 'Connected. Returning to Settings…';
                window.location = data.redirect;
                return;
            }
            if (data.status === 'pending') {
                statusEl.textContent = 'Waiting for approval in ARK Platform…';
            } else if (data.status === 'expired' || data.status === 'cancelled') {
                statusEl.textContent = 'This connection request expired. Start again from Settings.';
                return;
            } else if (data.status === 'error') {
                statusEl.textContent = data.message || 'Could not check connection status.';
            }
        } catch (e) {
            statusEl.textContent = 'Still waiting…';
        }
        if (tries < 90) {
            setTimeout(tick, 2000);
        }
    }

    setTimeout(tick, 1500);
})();
</script>
</x-operations.app>
