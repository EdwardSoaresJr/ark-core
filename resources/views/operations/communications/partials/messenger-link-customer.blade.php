@php
    /** @var array<string, mixed> $row */
@endphp

<div
    x-data="arkMessengerLinkCustomer(@js([
        'linkUrl' => $row['link_customer_url'] ?? '',
        'searchUrl' => route('operations.intake.customers.search'),
    ]))"
    class="inline-flex flex-wrap items-center gap-1"
>
    <button
        type="button"
        class="ops-call-queue__action"
        @click="toggle()"
        x-text="open ? 'Cancel link' : 'Link Customer'"
    >Link Customer</button>

    <div x-show="open" x-cloak class="mt-2 w-full basis-full space-y-2 rounded-sm border border-slate-200 bg-white p-2">
        <label class="block text-[11px] font-semibold text-slate-600">
            Search customer
            <input
                x-ref="searchInput"
                x-model="query"
                type="search"
                autocomplete="off"
                placeholder="Name, phone, email, plate, or VIN"
                class="mt-1 h-8 w-full min-w-[14rem] rounded-sm border border-slate-300 px-2 text-xs text-slate-900"
            >
        </label>

        <p x-show="searchLoading" x-cloak class="text-[11px] font-medium text-slate-500">Searching…</p>

        <div x-ref="results" class="max-h-48 overflow-y-auto"></div>

        <div x-show="selectedCustomerId" x-cloak class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2">
            <p class="text-xs font-semibold text-slate-700">
                Link to <span x-text="selectedCustomerName"></span>
            </p>
            <button
                type="button"
                class="ops-call-queue__action ops-call-queue__action--primary"
                @click="submitLink()"
                :disabled="linking"
            >
                <span x-show="! linking">Confirm link</span>
                <span x-show="linking" x-cloak>Linking…</span>
            </button>
            <button type="button" class="ops-call-queue__action ops-call-queue__action--ghost" @click="clearSelection()">
                Clear
            </button>
        </div>

        <p x-show="error" x-cloak class="text-xs font-semibold text-rose-700" x-text="error"></p>
    </div>
</div>
