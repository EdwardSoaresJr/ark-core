{{-- Tabler markup for the arkRoMention suggestions; the repair order workspace keeps repair-order-mention-suggest. --}}
<div
    class="dropdown-menu show w-100 mt-1"
    x-show="open && matches.length > 0"
    x-cloak
    role="listbox"
    aria-label="Previous visits"
    data-ro-mention-menu
>
    <template x-for="(row, index) in matches" :key="row.number">
        <button
            type="button"
            class="dropdown-item d-block"
            :class="{ 'active': index === activeIndex }"
            role="option"
            @mousedown.prevent="choose(row)"
        >
            <span class="d-block fw-medium" x-text="row.label"></span>
            <span class="d-block small text-secondary" x-text="row.detail"></span>
        </button>
    </template>
</div>
