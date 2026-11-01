@php
    /** @var string $name */
    /** @var string $value */
    /** @var list<array{key: string, label: string, swatch: string}> $options */
    $value = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::normalize($value ?? null);
    $labelledBy = $labelledBy ?? null;
@endphp

<div
    class="dropdown"
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
        class="form-select d-flex align-items-center gap-2 text-start"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
        @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
    >
        <span class="avatar avatar-xs rounded flex-shrink-0" :style="`background: ${selected?.swatch || '#94a3b8'}`" aria-hidden="true"></span>
        <span class="text-truncate" x-text="selected?.label || 'Color'"></span>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        class="dropdown-menu show w-100"
        role="listbox"
    >
        <template x-for="option in options" :key="option.key">
            <button
                type="button"
                class="dropdown-item d-flex align-items-center gap-2"
                :class="{ 'active': option.key === value }"
                @click="choose(option.key)"
                role="option"
                :aria-selected="(option.key === value).toString()"
            >
                <span class="avatar avatar-xs rounded flex-shrink-0" :style="`background: ${option.swatch}`" aria-hidden="true"></span>
                <span x-text="option.label"></span>
            </button>
        </template>
    </div>
</div>
