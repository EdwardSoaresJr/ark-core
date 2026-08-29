@php
    use App\Ark\Operations\Payments\SquareConfiguration;

    $square = app(SquareConfiguration::class);
    $integrations = $shopIntegrations ?? \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();
    $squareConfigured = $square->configured();
    $squarePairing = session('square_terminal_pairing');
@endphp

<section x-show="active === 'payments'" x-cloak class="space-y-3">
    <div class="border border-slate-300 bg-white p-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Square Payments</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Card capture inside ARK</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Save Square API credentials here. Secrets are encrypted in the shop database.
                <x-operations.learn.guide-link role="owner" article="square-payments-setup" :label="\App\Support\Branding\Branding::learnName().' → Square payments setup'" class="font-semibold text-slate-700 decoration-slate-300 hover:text-slate-950" />.
            </p>
        </div>

        <div class="mt-3 rounded-sm border px-3 py-2 text-xs font-semibold {{ \App\Ark\Operations\Payments\SquareSdk::adapterPackagePresent() ? ($squareConfigured ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900') : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            @if (! \App\Ark\Operations\Payments\SquareSdk::adapterPackagePresent())
                Square adapter not installed. Core ARK does not bundle Square.
                Install explicitly on the server: <code class="font-mono">{{ \App\Ark\Operations\Payments\SquareSdk::adapterPackageHint() }}</code>
                — then add credentials below.
            @elseif ($squareConfigured)
                Square API credentials detected. Environment: {{ $square->environment() }}.
                @if ($integrations->squareCredentialSource() === 'env')
                    <span class="font-normal">Using server <code>.env</code> fallback — save here to move credentials into Settings.</span>
                @endif
            @else
                Add Square credentials below before enabling payments.
            @endif
        </div>

        @if ($errors->has('square_terminal_pairing') || $errors->has('square') || $errors->has('square_location_id'))
            <div class="mt-3 rounded-sm border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-900">
                {{ $errors->first('square_terminal_pairing') ?: $errors->first('square') ?: $errors->first('square_location_id') }}
            </div>
        @endif

        @if (is_array($squarePairing))
            <div
                class="mt-3 rounded-sm border border-sky-200 bg-sky-50 px-3 py-2"
                x-data="{
                    pairingStatus: @js($squarePairing['status'] ?? 'UNPAIRED'),
                    pairingMessage: null,
                    async checkPairing() {
                        const response = await fetch(@js(route('operations.settings.shop.payments.square-terminal-device-code.show', ['deviceCodeId' => $squarePairing['id']])), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });

                        if (! response.ok) {
                            this.pairingMessage = 'Could not refresh pairing status.';
                            return;
                        }

                        const payload = await response.json();
                        this.pairingStatus = payload.status;

                        if (payload.paired) {
                            this.pairingMessage = 'Reader paired. Device ID saved — click Save Square settings if needed, then test Charge card on a RO.';
                            const input = document.querySelector('input[name=square_terminal_device_id]');
                            if (input && payload.device_id) {
                                input.value = payload.device_id;
                            }
                        }
                    },
                }"
                x-init="if (pairingStatus !== 'PAIRED') { setInterval(() => checkPairing(), 5000); }"
            >
                <p class="text-[10px] font-bold uppercase tracking-wide text-sky-700">Active pairing code</p>
                <p class="mt-1 font-mono text-2xl font-black tracking-[0.2em] text-slate-950">{{ $squarePairing['code'] }}</p>
                <p class="mt-1 text-[11px] text-slate-600">
                    On the reader: sign out → tap <strong>Device Code</strong> → enter this code within 5 minutes.
                </p>
                <p class="mt-1 text-[11px] text-slate-600">
                    Status: <span class="font-semibold" x-text="pairingStatus">{{ $squarePairing['status'] ?? 'UNPAIRED' }}</span>
                    @if (! empty($squarePairing['pair_by']))
                        · expires {{ $squarePairing['pair_by'] }}
                    @endif
                </p>
                <p class="mt-1 text-[11px] text-slate-600" x-show="pairingMessage" x-text="pairingMessage"></p>
                <button type="button" class="mt-2 text-[11px] font-semibold text-sky-800 underline decoration-sky-300" @click="checkPairing()">Check pairing now</button>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.payments.square-terminal-device-code.store') }}" class="border border-slate-300 bg-white p-4">
        @csrf
        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Terminal pairing</p>
        <p class="mt-1 text-[11px] leading-4 text-slate-600">
            Generate a Square <code>TERMINAL_API</code> device code, then pair the physical reader in Connected mode.
            <x-operations.learn.guide-link role="owner" article="square-payments-setup" :label="\App\Support\Branding\Branding::learnName()" class="font-semibold text-slate-700 decoration-slate-300 hover:text-slate-950" />
            · <a href="https://developer.squareup.com/docs/terminal-api/integrate-square-terminal" target="_blank" rel="noopener" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Square docs</a>.
        </p>

        @if ($square->isSandbox())
            <p class="mt-2 text-[11px] font-semibold text-amber-800">Sandbox cannot generate live reader codes. Use Square’s sandbox test <code>device_id</code> values, or switch Environment to production and save credentials first.</p>
        @elseif ($squareConfigured)
            <div class="mt-3 flex flex-wrap items-end gap-2">
                <label class="min-w-[12rem] flex-1">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Reader label (optional)</span>
                    <input type="text" name="name" value="ARK Counter" class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800" maxlength="64">
                </label>
                <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                    Generate pairing code
                </button>
            </div>
        @else
            <p class="mt-2 text-[11px] text-slate-500">Save Square credentials in the section below first, then generate a pairing code.</p>
        @endif
    </form>

    <form method="POST" action="{{ route('operations.settings.shop.payments.update') }}" class="space-y-3 border border-slate-300 bg-white p-4">
        @csrf
        @method('PATCH')

        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Square credentials and capture surfaces</p>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Application ID</span>
                <input
                    type="text"
                    name="square_application_id"
                    value="{{ old('square_application_id', $settings->square_application_id) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="sq0idp-…"
                    autocomplete="off"
                >
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Location ID</span>
                <input
                    type="text"
                    name="square_location_id"
                    value="{{ old('square_location_id', $settings->square_location_id) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    autocomplete="off"
                >
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Access token</span>
                <input
                    type="password"
                    name="square_access_token"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $integrations->hasStoredSquareAccessToken() ? 'Saved — leave blank to keep' : 'Production or sandbox token' }}"
                    autocomplete="new-password"
                >
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Webhook signature key</span>
                <input
                    type="password"
                    name="square_webhook_signature_key"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $integrations->hasStoredSquareWebhookSignatureKey() ? 'Saved — leave blank to keep' : 'From Square webhook subscription' }}"
                    autocomplete="new-password"
                >
            </label>

            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Environment</span>
                <select name="square_environment" class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800">
                    @php $squareEnvironment = old('square_environment', $settings->square_environment ?? $square->environment()); @endphp
                    <option value="sandbox" @selected($squareEnvironment === 'sandbox')>Sandbox</option>
                    <option value="production" @selected($squareEnvironment === 'production')>Production</option>
                </select>
            </label>
        </div>

        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <input type="hidden" name="square_enabled" value="0">
            <input type="checkbox" name="square_enabled" value="1" @checked(old('square_enabled', $settings->square_enabled))>
            Enable Square payments in ARK
        </label>

        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Terminal device ID</span>
            <input
                type="text"
                name="square_terminal_device_id"
                value="{{ old('square_terminal_device_id', $settings->square_terminal_device_id) }}"
                class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                placeholder="Filled automatically after pairing, or paste from Square Dashboard → Devices"
            >
            <span class="mt-1 block text-[11px] leading-4 text-slate-500">Use the paired <code>device_id</code>, not the sticker serial. ARK auto-fills this when pairing completes.</span>
        </label>

        <div class="grid gap-2 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_terminal_enabled" value="0">
                <input type="checkbox" name="square_terminal_enabled" value="1" @checked(old('square_terminal_enabled', $settings->square_terminal_enabled))>
                Counter terminal
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_keyed_enabled" value="0">
                <input type="checkbox" name="square_keyed_enabled" value="1" @checked(old('square_keyed_enabled', $settings->square_keyed_enabled))>
                Counter keyed entry
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_portal_pay_enabled" value="0">
                <input type="checkbox" name="square_portal_pay_enabled" value="1" @checked(old('square_portal_pay_enabled', $settings->square_portal_pay_enabled))>
                Customer portal pay
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="square_email_pay_enabled" value="0">
                <input type="checkbox" name="square_email_pay_enabled" value="1" @checked(old('square_email_pay_enabled', $settings->square_email_pay_enabled))>
                Emailed invoice pay link
            </label>
        </div>

        <p class="text-xs leading-5 text-slate-500">
            Webhook URL: <span class="font-mono text-[11px] text-slate-700">{{ route('webhooks.square') }}</span>
        </p>

        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
            Save Square settings
        </button>
    </form>
</section>
