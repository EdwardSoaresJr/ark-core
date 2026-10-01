@php
    $deferredItems = $priorVisitMentions['deferred'] ?? [];
    $canAddDeferred = (bool) ($canAuthorRepairOrder ?? false) && ! ($isTerminal ?? false);
@endphp
@if ($canAddDeferred)
    @foreach ($deferredItems as $item)
        @if (($item['add_url'] ?? '') !== '')
            <form
                id="deferred-add-{{ $item['key'] }}"
                method="POST"
                action="{{ $item['add_url'] }}"
                class="hidden"
                data-refresh-scope="worksheet"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
            </form>
        @endif
    @endforeach
@endif
