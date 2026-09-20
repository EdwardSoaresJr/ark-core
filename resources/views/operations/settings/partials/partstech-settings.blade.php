@php
    $integrations = $shopIntegrations ?? \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();
    $platformPartsCatalog = \App\Ark\Platform\Parts\ManagedPartsGate::platformCatalog();
    $catalogColors = \App\Ark\Operations\Parts\PartsCatalogButtonColor::shopMap();
    $partsTechColor = old('partstech_color', $catalogColors['partstech'] ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::YELLOW);
    $repairLinkColor = old('repairlink_color', $catalogColors['repairlink'] ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::BLUE);
    $nexpartColor = old('nexpart_color', $catalogColors['nexpart'] ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::NAVY);
@endphp

<section x-show="active === 'partstech'" x-cloak>
    @if ($platformPartsCatalog)
        <div class="space-y-3 border border-slate-300 bg-white p-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">PartsTech</p>
                <h2 class="mt-1 text-lg font-black text-slate-950">Parts catalog</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    The shop PartsTech account is managed by ARK Platform. Optional personal seats stay on each person’s profile (avatar menu → Profile).
                </p>
            </div>
            <div class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800">
                Shop catalog access is granted and configured in ARK Platform. This shop does not store a shop-wide PartsTech password.
            </div>
            <form method="POST" action="{{ route('operations.settings.shop.partstech.update') }}" class="max-w-xs">
                @csrf
                @method('PATCH')
                @include('operations.parts.partials.catalog-color-select', [
                    'name' => 'partstech_color',
                    'value' => $partsTechColor,
                    'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                    'selectClass' => 'rounded-sm',
                ])
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                        Save button color
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="space-y-3 border border-slate-300 bg-white p-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">PartsTech</p>
                <h2 class="mt-1 text-lg font-black text-slate-950">Parts catalog</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    This shop does not store a shop-wide PartsTech password. Connect ARK Platform for shop catalog access. Optional personal seats stay on each person’s profile (avatar menu → Profile).
                </p>
            </div>
            <form method="POST" action="{{ route('operations.settings.shop.partstech.update') }}" class="max-w-xs">
                @csrf
                @method('PATCH')
                @include('operations.parts.partials.catalog-color-select', [
                    'name' => 'partstech_color',
                    'value' => $partsTechColor,
                    'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                    'selectClass' => 'rounded-sm',
                ])
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                        Save button color
                    </button>
                </div>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('operations.settings.shop.repairlink.update') }}" class="mt-3 space-y-3 border border-slate-300 bg-white p-4">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">RepairLink</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">New-tab catalog link</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Opens RepairLink in a new tab from the estimate toolbar. It does not replace PartsTech on the main catalog button. ARK does not sign in, pull a cart, or send vehicle data in the URL. VIN is copied to the clipboard when present.
            </p>
        </div>

        <div class="rounded-sm border px-3 py-2 text-xs font-semibold {{ $integrations->repairLinkConfigured() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-800' }}">
            @if ($integrations->repairLinkConfigured())
                RepairLink is available on the estimate toolbar.
            @else
                RepairLink stays closed until it is enabled with an https:// launch address.
            @endif
        </div>

        @error('repairlink_url')
            <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
        @enderror

        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <input type="hidden" name="repairlink_enabled" value="0">
            <input type="checkbox" name="repairlink_enabled" value="1" @checked(old('repairlink_enabled', $settings->repairlink_enabled ?? false))>
            Show RepairLink on the estimate toolbar
        </label>

        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Launch URL</span>
            <input
                type="text"
                name="repairlink_url"
                value="{{ old('repairlink_url', $settings->repairlink_url ?? '') }}"
                class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                placeholder="https://repairlinkshop.com"
                autocomplete="off"
            >
            <p class="mt-1 text-xs leading-5 text-slate-500">HTTPS only. Enter the shop’s RepairLink address. ARK will add https:// if the scheme is missing.</p>
        </label>

        <div class="max-w-xs">
            @include('operations.parts.partials.catalog-color-select', [
                'name' => 'repairlink_color',
                'value' => $repairLinkColor,
                'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                'selectClass' => 'rounded-sm',
            ])
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save RepairLink settings
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('operations.settings.shop.nexpart.update') }}" class="mt-3 space-y-3 border border-slate-300 bg-white p-4">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Nexpart</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Catalog or new-tab link</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                If this shop pays for ARK Platform parts catalog, Nexpart sits on the estimate catalog button (Open and VIN copy; no cart pull). Otherwise it opens in a new tab like RepairLink. ARK does not sign in or pull a Nexpart cart.
            </p>
        </div>

        <div class="rounded-sm border px-3 py-2 text-xs font-semibold {{ $integrations->nexpartConfigured() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-800' }}">
            @if ($integrations->nexpartConfigured())
                Nexpart is available on the estimate toolbar.
            @else
                Nexpart stays closed until it is enabled with an https:// launch address.
            @endif
        </div>

        @error('nexpart_url')
            <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
        @enderror

        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <input type="hidden" name="nexpart_enabled" value="0">
            <input type="checkbox" name="nexpart_enabled" value="1" @checked(old('nexpart_enabled', $settings->nexpart_enabled ?? false))>
            Enable Nexpart for this shop
        </label>

        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Launch URL</span>
            <input
                type="text"
                name="nexpart_url"
                value="{{ old('nexpart_url', $settings->nexpart_url ?? '') }}"
                class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                placeholder="https://"
                autocomplete="off"
            >
            <p class="mt-1 text-xs leading-5 text-slate-500">HTTPS only. ARK will add https:// if the scheme is missing.</p>
        </label>

        <div class="max-w-xs">
            @include('operations.parts.partials.catalog-color-select', [
                'name' => 'nexpart_color',
                'value' => $nexpartColor,
                'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                'selectClass' => 'rounded-sm',
            ])
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save Nexpart settings
            </button>
        </div>
    </form>

    @php
        $shopCatalogLinks = \App\Ark\Operations\Parts\PartsCatalogLinks::customForShop();
    @endphp

    <form method="POST" action="{{ route('operations.settings.shop.catalog-links.update') }}" class="mt-3 space-y-3 border border-slate-300 bg-white p-4">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">More catalogs</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Shop catalog links</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Add O’Reilly Pro, First Call, AutoZone Pro, or any other https:// catalog. Choose Catalog to put it on the estimate button like PartsTech, or Link to open it in a new tab. PartsTech cannot be added or edited here.
            </p>
        </div>

        @error('catalog_links')
            <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
        @enderror

        @foreach ($shopCatalogLinks as $index => $link)
            <div class="grid gap-2 border border-slate-200 p-3 sm:grid-cols-2 lg:grid-cols-[1fr_1.4fr_8rem_8rem_auto] sm:items-end">
                <input type="hidden" name="links[{{ $index }}][key]" value="{{ $link['key'] }}">
                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Name</span>
                    <input
                        type="text"
                        name="links[{{ $index }}][label]"
                        value="{{ old('links.'.$index.'.label', $link['label']) }}"
                        maxlength="64"
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                    >
                </label>
                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Launch URL</span>
                    <input
                        type="text"
                        name="links[{{ $index }}][url]"
                        value="{{ old('links.'.$index.'.url', $link['url']) }}"
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                        autocomplete="off"
                    >
                </label>
                @include('operations.parts.partials.catalog-mode-select', [
                    'name' => 'links['.$index.'][mode]',
                    'value' => old('links.'.$index.'.mode', $link['mode'] ?? 'link'),
                    'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                    'selectClass' => 'rounded-sm',
                ])
                @include('operations.parts.partials.catalog-color-select', [
                    'name' => 'links['.$index.'][color]',
                    'value' => old('links.'.$index.'.color', $link['color'] ?? 'slate'),
                    'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                    'selectClass' => 'rounded-sm',
                ])
                <label class="inline-flex min-h-9 items-center gap-2 text-xs font-medium text-slate-600">
                    <input type="checkbox" name="links[{{ $index }}][delete]" value="1">
                    Remove
                </label>
            </div>
        @endforeach

        <div class="grid gap-2 border border-dashed border-slate-300 p-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Add name</span>
                <input
                    type="text"
                    name="create[label]"
                    value="{{ old('create.label') }}"
                    maxlength="64"
                    placeholder="O’Reilly Pro"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                >
            </label>
            <label class="block">
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Add URL</span>
                <input
                    type="text"
                    name="create[url]"
                    value="{{ old('create.url') }}"
                    placeholder="https://"
                    class="mt-1 h-9 w-full rounded-sm border-slate-300 font-mono text-sm text-slate-800"
                    autocomplete="off"
                >
            </label>
            @include('operations.parts.partials.catalog-mode-select', [
                'name' => 'create[mode]',
                'value' => old('create.mode', 'link'),
                'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                'selectClass' => 'rounded-sm',
            ])
            @include('operations.parts.partials.catalog-color-select', [
                'name' => 'create[color]',
                'value' => old('create.color', ''),
                'labelClass' => 'text-xs font-bold uppercase tracking-[0.08em] text-slate-400',
                'selectClass' => 'rounded-sm',
                'allowAutomatic' => true,
            ])
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                Save catalog links
            </button>
        </div>
    </form>
</section>
