@php
    $integrations = $shopIntegrations ?? \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();
    $partsTechCatalogReady = $integrations->partsTechCatalogConfigured();
    $partsTechQuoteReady = $integrations->partsTechQuoteImportConfigured();
@endphp

<section x-show="active === 'partstech'" x-cloak>
    <form method="POST" action="{{ route('operations.settings.shop.partstech.update') }}" class="space-y-3 border border-slate-300 bg-white p-4">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">PartsTech</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Parts catalog and quote import</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Save PartsTech credentials here. Secrets are encrypted in the shop database.
                {{ $integrations->credentialSourceLabel($integrations->partsTechCredentialSource()) }}
                For separate advisor seats, each person adds their PartsTech username and password on their profile (avatar menu → Profile).
            </p>
        </div>

        <div class="rounded-sm border px-3 py-2 text-xs font-semibold {{ $partsTechCatalogReady ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            @if ($partsTechCatalogReady)
                PartsTech catalog launch is ready.
                @if ($partsTechQuoteReady)
                    Quote pull and cart preparation are also configured.
                @else
                    Add a shop password below to enable quote pull and cart preparation.
                @endif
            @else
                PartsTech is not fully configured. Username plus API key or password is required.
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Base URL</span>
                <input
                    type="url"
                    name="partstech_base_url"
                    value="{{ old('partstech_base_url', $settings->partstech_base_url ?: 'https://app.partstech.com') }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="https://app.partstech.com"
                    autocomplete="off"
                >
            </label>

            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Catalog path</span>
                <input
                    type="text"
                    name="partstech_catalog_path"
                    value="{{ old('partstech_catalog_path', $settings->partstech_catalog_path) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="Optional path after base URL"
                    autocomplete="off"
                >
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Shop username</span>
                <input
                    type="text"
                    name="partstech_username"
                    value="{{ old('partstech_username', $settings->partstech_username) }}"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    autocomplete="off"
                >
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">API key</span>
                <input
                    type="password"
                    name="partstech_api_key"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $integrations->hasStoredPartsTechApiKey() ? 'Saved — leave blank to keep' : 'VIN decode / catalog API key' }}"
                    autocomplete="new-password"
                >
            </label>

            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Shop password</span>
                <input
                    type="password"
                    name="partstech_password"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $integrations->hasStoredPartsTechPassword() ? 'Saved — leave blank to keep' : 'Required for quote pull and cart preparation' }}"
                    autocomplete="new-password"
                >
                <p class="mt-1 text-xs leading-5 text-slate-500">Use the same PartsTech shop user that advisors sign into when ordering parts.</p>
            </label>
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save PartsTech settings
            </button>
        </div>
    </form>
</section>
