@php
    $partsCatalogs = $partsCatalogs ?? [];
    $partsCatalogLinks = $partsCatalogLinks ?? [];
    $shopRepairLinkUrl = $shopRepairLinkUrl ?? null;
    $shopNexpartUrl = $shopNexpartUrl ?? null;
    $userRepairLinkUrl = $userRepairLinkUrl ?? null;
    $userNexpartUrl = $userNexpartUrl ?? null;
    $userRepairLinkColor = $userRepairLinkColor ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::BLUE;
    $userNexpartColor = $userNexpartColor ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::NAVY;
    $defaultChoices = collect($partsCatalogs)
        ->filter(fn (array $catalog): bool => ($catalog['mode'] ?? 'catalog') === 'catalog' && ($catalog['can_open'] ?? false))
        ->values();

    if ($defaultChoices->isEmpty()) {
        $defaultChoices = collect($partsCatalogs)->values();
    }

    $currentDefault = old(
        'default_parts_catalog',
        $user->default_parts_catalog ?: ($defaultChoices[0]['key'] ?? ''),
    );
@endphp

<section>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Estimate toolbar</p>
        <h2 class="text-base font-black text-slate-950">Parts catalogs</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Default catalog for new repair orders, plus extra catalog buttons and new-tab links. PartsTech is managed by ARK Platform and cannot be edited here.
        </p>
    </div>

    <form method="post" action="{{ route('profile.catalogs.update') }}" class="mt-4 max-w-2xl space-y-5">
        @csrf
        @method('patch')

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Default catalog</span>
            <select
                name="default_parts_catalog"
                class="mt-1 block h-10 w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500"
            >
                @foreach ($defaultChoices as $catalog)
                    <option value="{{ $catalog['key'] }}" @selected($currentDefault === $catalog['key'])>
                        {{ $catalog['label'] }}{{ $catalog['can_open'] ? '' : ' (add URL)' }}
                    </option>
                @endforeach
            </select>
        </label>

        <div class="space-y-3 border border-slate-200 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">PartsTech</p>
            <p class="text-sm text-slate-700">ARK Platform catalog. Open and Pull Cart stay on the estimate toolbar. The launch URL cannot be edited.</p>
        </div>

        <div class="space-y-3 border border-slate-200 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">RepairLink</p>
            <p class="text-xs leading-5 text-slate-500">Opens in a new tab. It does not replace PartsTech on the main catalog button.</p>
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Launch URL</span>
                <input
                    type="text"
                    name="repairlink_url"
                    value="{{ old('repairlink_url', $userRepairLinkUrl ?? '') }}"
                    class="mt-1 h-9 w-full border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $shopRepairLinkUrl ?: 'https://repairlinkshop.com' }}"
                    autocomplete="off"
                >
            </label>
            <div class="max-w-xs">
                @include('operations.parts.partials.catalog-color-select', [
                    'name' => 'repairlink_color',
                    'value' => old('repairlink_color', $userRepairLinkColor),
                ])
            </div>
            <p class="text-xs leading-5 text-slate-500">
                Leave blank to use the shop RepairLink address{{ $shopRepairLinkUrl ? ' ('.$shopRepairLinkUrl.')' : '' }}.
            </p>
            @error('repairlink_url')
                <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-3 border border-slate-200 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nexpart</p>
            <p class="text-xs leading-5 text-slate-500">Catalog on the estimate button only when ARK Platform parts catalog is paid. Otherwise it opens in a new tab.</p>
            <label class="block">
                <span class="text-xs font-medium text-slate-500">Launch URL</span>
                <input
                    type="text"
                    name="nexpart_url"
                    value="{{ old('nexpart_url', $userNexpartUrl ?? '') }}"
                    class="mt-1 h-9 w-full border-slate-300 font-mono text-sm text-slate-800"
                    placeholder="{{ $shopNexpartUrl ?: 'https://' }}"
                    autocomplete="off"
                >
            </label>
            <div class="max-w-xs">
                @include('operations.parts.partials.catalog-color-select', [
                    'name' => 'nexpart_color',
                    'value' => old('nexpart_color', $userNexpartColor),
                ])
            </div>
            <p class="text-xs leading-5 text-slate-500">
                Leave blank to use the shop Nexpart address{{ $shopNexpartUrl ? ' ('.$shopNexpartUrl.')' : '' }}.
            </p>
            @error('nexpart_url')
                <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">My catalog links</p>
            <p class="text-xs leading-5 text-slate-500">O’Reilly Pro, First Call, AutoZone Pro, or any other https:// catalog. Catalog sits on the estimate button. Link opens in a new tab. These are only on your estimate toolbar.</p>

            @error('catalog_links')
                <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
            @enderror

            @foreach ($partsCatalogLinks as $index => $link)
                <div class="grid gap-2 border border-slate-200 p-3 sm:grid-cols-2 lg:grid-cols-[1fr_1.4fr_8rem_8rem_auto] sm:items-end">
                    <input type="hidden" name="links[{{ $index }}][key]" value="{{ $link['key'] }}">
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">Name</span>
                        <input
                            type="text"
                            name="links[{{ $index }}][label]"
                            value="{{ old('links.'.$index.'.label', $link['label']) }}"
                            maxlength="64"
                            class="mt-1 h-9 w-full border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">URL</span>
                        <input
                            type="text"
                            name="links[{{ $index }}][url]"
                            value="{{ old('links.'.$index.'.url', $link['url']) }}"
                            class="mt-1 h-9 w-full border-slate-300 font-mono text-sm text-slate-800"
                            autocomplete="off"
                        >
                    </label>
                    @include('operations.parts.partials.catalog-mode-select', [
                        'name' => 'links['.$index.'][mode]',
                        'value' => old('links.'.$index.'.mode', $link['mode'] ?? 'link'),
                    ])
                    @include('operations.parts.partials.catalog-color-select', [
                        'name' => 'links['.$index.'][color]',
                        'value' => old('links.'.$index.'.color', $link['color'] ?? 'slate'),
                    ])
                    <label class="inline-flex min-h-9 items-center gap-2 text-xs font-medium text-slate-600">
                        <input type="checkbox" name="links[{{ $index }}][delete]" value="1">
                        Remove
                    </label>
                </div>
            @endforeach

            <div class="grid gap-2 border border-dashed border-slate-300 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Add name</span>
                    <input
                        type="text"
                        name="create[label]"
                        value="{{ old('create.label') }}"
                        maxlength="64"
                        placeholder="O’Reilly Pro"
                        class="mt-1 h-9 w-full border-slate-300 text-sm text-slate-800"
                    >
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Add URL</span>
                    <input
                        type="text"
                        name="create[url]"
                        value="{{ old('create.url') }}"
                        placeholder="https://"
                        class="mt-1 h-9 w-full border-slate-300 font-mono text-sm text-slate-800"
                        autocomplete="off"
                    >
                </label>
                @include('operations.parts.partials.catalog-mode-select', [
                    'name' => 'create[mode]',
                    'value' => old('create.mode', 'link'),
                ])
                @include('operations.parts.partials.catalog-color-select', [
                    'name' => 'create[color]',
                    'value' => old('create.color', ''),
                    'allowAutomatic' => true,
                ])
            </div>
        </div>

        <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
            Save catalogs
        </button>
    </form>
</section>
