@php
    $mentionSuggestions = $priorVisitMentions['suggestions'] ?? [];
    $deferredItems = $priorVisitMentions['deferred'] ?? [];
    $visitCards = array_slice($mentionSuggestions, 0, 5);
    $visitInsert = $visitInsert ?? 'insertChip';
    $canAddDeferred = (bool) ($canAuthorRepairOrder ?? false) && ! ($isTerminal ?? false);
@endphp
@if ($visitCards !== [] || $deferredItems !== [])
    <div class="row g-2 mt-2" data-vehicle-memory>
        <div class="col-6" data-vehicle-memory-visits>
            <div class="subheader mb-1">Previous visits</div>
            <div class="list-group" role="list">
                @forelse ($visitCards as $visit)
                    <button
                        type="button"
                        class="list-group-item list-group-item-action py-2 small"
                        role="listitem"
                        @click="{{ $visitInsert }}({{ \Illuminate\Support\Js::from($visit) }})"
                    >
                        <span class="d-block fw-medium">{{ $visit['label'] }}</span>
                        @if (($visit['detail'] ?? '') !== '')
                            <span class="d-block text-secondary">{{ $visit['detail'] }}</span>
                        @endif
                    </button>
                @empty
                    <p class="list-group-item small text-secondary mb-0">None for this vehicle</p>
                @endforelse
            </div>
        </div>
        <div class="col-6" data-vehicle-memory-deferred>
            <div class="subheader mb-1">Deferred</div>
            <div class="list-group" role="list">
                @forelse ($deferredItems as $item)
                    <div class="list-group-item d-flex align-items-start gap-2 py-2 small" role="listitem">
                        <div class="flex-fill min-w-0">
                            <span class="d-block fw-medium">{{ $item['title'] }}</span>
                            @if (($item['detail'] ?? '') !== '' || ($item['amount_label'] ?? '') !== '')
                                <span class="d-block text-secondary">
                                    {{ implode(' · ', array_values(array_filter([$item['detail'] ?? '', $item['amount_label'] ?? '']))) }}
                                </span>
                            @endif
                        </div>
                        @if ($canAddDeferred && ($item['add_url'] ?? '') !== '')
                            <button
                                type="submit"
                                class="btn btn-sm"
                                form="deferred-add-{{ $item['key'] }}"
                            >Add</button>
                        @endif
                    </div>
                @empty
                    <p class="list-group-item small text-secondary mb-0">None for this vehicle</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
