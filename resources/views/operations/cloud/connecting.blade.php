<x-operations.app title="Connecting ARK Platform">
<div class="mx-auto max-w-lg px-4 py-10">
    <h1 class="text-xl font-semibold text-slate-950">Connecting ARK Platform</h1>
    <p class="mt-2 text-sm text-slate-600">
        Finish signing in on ARK Platform and approve this Box. This page will update when the connection is ready.
        ARK does not sign you into Cloud automatically — Cloud asks for your account as usual.
    </p>

    @if ($pairingCode)
        <div class="mt-4 rounded-sm border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
            <p>If you need the pairing code instead:</p>
            <p class="mt-1 font-mono text-sm tracking-widest">{{ $pairingCode }}</p>
            @if ($cloudPairingUrl)
                <p class="mt-2"><a class="underline" href="{{ $cloudPairingUrl }}" target="_blank" rel="noopener">Open pairing page</a></p>
            @endif
        </div>
    @endif

    <p id="cloud-connect-status" class="mt-6 text-sm font-medium text-slate-800">Waiting for approval…</p>

    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="inline-flex min-h-9 items-center rounded-sm border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-800">
            Back to Settings
        </a>
        <form method="POST" action="{{ route('operations.settings.shop.ark-cloud.claim') }}">
            @csrf
            @if ($pairingPublicId)
                <input type="hidden" name="pairing_public_id" value="{{ $pairingPublicId }}">
            @endif
            <button type="submit" class="inline-flex min-h-9 items-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white">
                Check now
            </button>
        </form>
    </div>
</div>

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
