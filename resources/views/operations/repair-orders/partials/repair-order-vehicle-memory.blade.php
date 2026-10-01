@php
    $mentionSuggestions = $priorVisitMentions['suggestions'] ?? [];
    $deferredItems = $priorVisitMentions['deferred'] ?? [];
    $visitCards = array_slice($mentionSuggestions, 0, 5);
    $visitInsert = $visitInsert ?? 'insertChip';
    $canAddDeferred = (bool) ($canAuthorRepairOrder ?? false) && ! ($isTerminal ?? false);
@endphp
@if ($visitCards !== [] || $deferredItems !== [])
    <div class="ark-vehicle-memory grid grid-cols-2 gap-3">
        <div class="ark-vehicle-memory__column ark-vehicle-memory__visits">
            <p class="ark-vehicle-memory__label">Previous visits</p>
            <div class="ark-vehicle-memory__list" role="list">
                @forelse ($visitCards as $visit)
                    <button
                        type="button"
                        class="ark-vehicle-memory__card"
                        role="listitem"
                        @click="{{ $visitInsert }}({{ \Illuminate\Support\Js::from($visit) }})"
                    >
                        <span class="ark-vehicle-memory__card-title">{{ $visit['label'] }}</span>
                        @if (($visit['detail'] ?? '') !== '')
                            <span class="ark-vehicle-memory__card-detail">{{ $visit['detail'] }}</span>
                        @endif
                    </button>
                @empty
                    <p class="ark-vehicle-memory__empty m-0 min-h-[5.5rem] rounded border border-dashed border-slate-200 p-2 text-xs text-slate-500">None for this vehicle</p>
                @endforelse
            </div>
        </div>
        <div class="ark-vehicle-memory__column ark-vehicle-memory__deferred">
            <p class="ark-vehicle-memory__label">Deferred</p>
            <div class="ark-vehicle-memory__list" role="list">
                @forelse ($deferredItems as $item)
                    <div class="ark-vehicle-memory__card ark-vehicle-memory__card--row" role="listitem">
                        <div class="ark-vehicle-memory__card-copy min-w-0">
                            <span class="ark-vehicle-memory__card-title">{{ $item['title'] }}</span>
                            @if (($item['detail'] ?? '') !== '' || ($item['amount_label'] ?? '') !== '')
                                <span class="ark-vehicle-memory__card-detail">
                                    {{ implode(' · ', array_values(array_filter([$item['detail'] ?? '', $item['amount_label'] ?? '']))) }}
                                </span>
                            @endif
                        </div>
                        @if ($canAddDeferred && ($item['add_url'] ?? '') !== '')
                            <button
                                type="submit"
                                class="ark-vehicle-memory__add"
                                form="deferred-add-{{ $item['key'] }}"
                            >Add</button>
                        @endif
                    </div>
                @empty
                    <p class="ark-vehicle-memory__empty m-0 min-h-[5.5rem] rounded border border-dashed border-slate-200 p-2 text-xs text-slate-500">None for this vehicle</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
