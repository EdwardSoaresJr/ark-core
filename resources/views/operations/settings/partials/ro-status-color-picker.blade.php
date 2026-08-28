@php
    /** @var string $name */
    /** @var string $value */
    /** @var list<array{key: string, label: string, swatch: string}> $options */
    $value = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::normalize($value ?? null);
@endphp

<div
    class="relative mt-1"
    x-data="{
        open: false,
        value: @js($value),
        options: @js($options),
        get selected() {
            return this.options.find((option) => option.key === this.value) ?? this.options[0];
        },
        choose(key) {
            this.value = key;
            this.open = false;
        },
    }"
    @keydown.escape.window="open = false"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <button
        type="button"
        class="flex w-full min-w-[10rem] items-center gap-2 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-left text-sm text-slate-950 hover:border-slate-400"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
    >
        <span
            class="inline-block h-5 w-5 shrink-0 rounded border border-slate-300"
            :style="`background: ${selected?.swatch || '#94a3b8'}`"
            aria-hidden="true"
        ></span>
        <span class="min-w-0 flex-1 truncate" x-text="selected?.label || 'Color'"></span>
        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        class="absolute left-0 z-30 mt-1 w-full min-w-[11rem] overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg"
        role="listbox"
    >
        <template x-for="option in options" :key="option.key">
            <button
                type="button"
                class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-slate-950 hover:bg-slate-50"
                :class="{ 'bg-slate-100 font-semibold': option.key === value }"
                @click="choose(option.key)"
                role="option"
                :aria-selected="(option.key === value).toString()"
            >
                <span
                    class="inline-block h-5 w-5 shrink-0 rounded border border-slate-300"
                    :style="`background: ${option.swatch}`"
                    aria-hidden="true"
                ></span>
                <span x-text="option.label"></span>
            </button>
        </template>
    </div>
</div>
