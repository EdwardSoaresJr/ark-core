@php
    $mentionSuggestions = $priorVisitMentions['suggestions'] ?? [];
@endphp
@if ($mentionSuggestions !== [])
    <div class="ark-ro-mention__chips" role="list">
        <p class="ark-ro-mention__hint">Previous visits — click or type @RO</p>
        @foreach ($mentionSuggestions as $visit)
            <button
                type="button"
                class="ark-ro-mention__chip{{ ! empty($visit['same_vehicle']) ? ' ark-ro-mention__chip--same' : '' }}"
                role="listitem"
                @click="insertChip({{ \Illuminate\Support\Js::from($visit) }})"
            >
                <span class="ark-ro-mention__chip-label">{{ $visit['label'] }}</span>
                @if (($visit['detail'] ?? '') !== '')
                    <span class="ark-ro-mention__chip-detail">{{ $visit['detail'] }}</span>
                @endif
            </button>
        @endforeach
    </div>
@endif

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
