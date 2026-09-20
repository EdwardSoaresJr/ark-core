@php
    $actionSuggestions = session('worksheet_repair_action_suggestions');
    $showActionSuggestions = is_array($actionSuggestions)
        && (int) ($actionSuggestions['concern_id'] ?? 0) === $concern->id
        && ! empty($actionSuggestions['titles'] ?? [])
        && $concern->workGroups->isEmpty()
        && ! ($isTerminal ?? false);
@endphp

@if ($showActionSuggestions)
    <div class="ops-scope-repair-action-suggestions border-b border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Suggested repairs</p>
        <p class="mt-0.5 text-[11px] text-slate-400">One tap — nothing is committed until you add lines.</p>
        <ul class="mt-2 flex flex-wrap gap-1.5">
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
                            class="rounded-sm border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400 hover:bg-slate-100"
                        >
                            {{ $title }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif
