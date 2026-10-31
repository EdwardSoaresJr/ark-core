@php
    $actionSuggestions = session('worksheet_repair_action_suggestions');
    $showActionSuggestions = is_array($actionSuggestions)
        && (int) ($actionSuggestions['concern_id'] ?? 0) === $concern->id
        && ! empty($actionSuggestions['titles'] ?? [])
        && $concern->workGroups->isEmpty()
        && ! ($isTerminal ?? false);
@endphp

@if ($showActionSuggestions)
    <div class="card-body border-bottom py-2" data-repair-action-suggestions>
        <div class="subheader">Suggested repairs</div>
        <p class="small text-secondary mb-2">One tap - nothing is committed until you add lines.</p>
        <ul class="list-unstyled d-flex flex-wrap gap-1 mb-0">
            @foreach ($actionSuggestions['titles'] as $title)
                <li>
                    <form
                        method="POST"
                        action="{{ route('operations.repair-orders.concerns.work-groups.store', [$repairOrder, $concern]) }}"
                        data-refresh-scope="worksheet"
                        data-continuity-focus="#repair-action-{{ $concern->id }}-{{ $loop->index }}"
                        @submit.prevent="submitWorksheetForm($event)"
                    >
                        @csrf
                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                        <input type="hidden" name="title" value="{{ $title }}">
                        <button
                            type="submit"
                            id="repair-action-{{ $concern->id }}-{{ $loop->index }}"
                            class="btn btn-sm"
                        >
                            {{ $title }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif
