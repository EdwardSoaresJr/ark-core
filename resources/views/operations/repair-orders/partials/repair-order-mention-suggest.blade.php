<div
    class="ark-ro-mention__menu"
    x-show="open && matches.length > 0"
    x-cloak
    role="listbox"
    aria-label="Previous visits"
>
    <template x-for="(row, index) in matches" :key="row.number">
        <button
            type="button"
            class="ark-ro-mention__option"
            :class="{ 'ark-ro-mention__option--active': index === activeIndex }"
            role="option"
            @mousedown.prevent="choose(row)"
        >
            <span class="font-semibold" x-text="row.label"></span>
            <span class="block text-[11px] text-slate-500" x-text="row.detail"></span>
        </button>
    </template>
</div>
