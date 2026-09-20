@php
    $activeType = $activeType ?? '';
@endphp

<div class="ops-line-type-picker" role="group" aria-label="Add line to this scope">
    <input type="hidden" name="type" :value="type">
    <div class="ops-line-type-picker__buttons">
        <button
            type="button"
            class="ops-line-type-btn ops-line-type-btn--labor"
            :class="type === 'labor' ? 'ops-line-type-btn--active' : ''"
            @click="selectLineType('labor')"
        >
            + Labor
        </button>
        <button
            type="button"
            class="ops-line-type-btn ops-line-type-btn--part"
            :class="type === 'part' ? 'ops-line-type-btn--active' : ''"
            @click="selectLineType('part')"
        >
            + Part
        </button>
        <button
            type="button"
            class="ops-line-type-btn ops-line-type-btn--note"
            :class="type === 'note' ? 'ops-line-type-btn--active' : ''"
            @click="selectLineType('note')"
        >
            + Note
        </button>
        <button
            type="button"
            class="ops-line-type-btn ops-line-type-btn--fee"
            :class="type === 'fee' ? 'ops-line-type-btn--active' : ''"
            @click="selectLineType('fee')"
        >
            + Fee
        </button>
        <button
            type="button"
            class="ops-line-type-btn ops-line-type-btn--sublet"
            :class="type === 'sublet' ? 'ops-line-type-btn--active' : ''"
            @click="selectLineType('sublet')"
        >
            + Sublet
        </button>
    </div>
</div>
