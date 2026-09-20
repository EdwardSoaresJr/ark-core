{{-- Reason for Visit — presentation only; author via workspace modal --}}
@php
    $visitReasonEditable = ! $isTerminal;
    $visitReasonText = trim((string) ($repairOrder->visit_reason ?? ''));
    $priorVisitMentions = $priorVisitMentions ?? ['suggestions' => [], 'href_by_number' => []];
    $visitReasonProposals = $visitReasonConcernProposals ?? [];
    $showVisitReasonProposals = $visitReasonEditable
        && $visitReasonText !== ''
        && $repairOrder->concerns->isEmpty()
        && is_array($visitReasonProposals)
        && $visitReasonProposals !== [];
@endphp
<div id="visit-reason" class="scroll-mt-6 border-x border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reason for Visit</h2>
            @if ($visitReasonText !== '')
                <p class="mt-1.5 whitespace-pre-line text-sm leading-5 text-slate-700">{!! \App\Ark\Operations\RepairOrders\RepairOrderMention::html($visitReasonText, $priorVisitMentions['href_by_number'] ?? []) !!}</p>
            @else
                <p class="mt-1.5 text-sm italic text-slate-400">Not recorded yet.</p>
            @endif
        </div>
        @if ($visitReasonEditable)
            <div class="flex shrink-0 items-center gap-2">
                @if ($visitReasonText !== '')
                    <button
                        type="button"
                        class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-800"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor-visit-reason', context: {}, invokeEl: $event.currentTarget } }))"
                    >
                        Rewrite
                    </button>
                @endif
                <button
                    type="button"
                    class="ops-builder-present-action"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'visit-reason', invokeEl: $event.currentTarget } }))"
                >
                    {{ $visitReasonText !== '' ? 'Edit' : 'Add' }}
                </button>
            </div>
        @endif
    </div>

    @if ($showVisitReasonProposals)
        @php
            $visitReasonProposalState = collect($visitReasonProposals)
                ->map(fn (array $proposal): array => [
                    'summary' => $proposal['summary'],
                    'scope_entry_kind' => $proposal['scope_entry_kind'],
                ])
                ->values()
                ->all();
        @endphp
        <div
            class="mt-2.5 border border-slate-200 bg-white px-3 py-2.5"
            x-data="{ proposals: @js($visitReasonProposalState) }"
        >
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Suggested concerns</p>
            <p class="mt-0.5 text-[11px] text-slate-500">From the reason for visit. Accept to create concerns — the visit reason stays unchanged.</p>
            <ul class="mt-2 space-y-1.5">
                @foreach ($visitReasonProposals as $index => $proposal)
                    <li class="flex flex-wrap items-center gap-2 rounded-sm border border-slate-200 bg-white px-2.5 py-1.5">
                        <div class="min-w-0 flex-1">
                            <label class="sr-only" for="visit-reason-proposal-{{ $index }}">Suggested concern {{ $index + 1 }}</label>
                            <input
                                id="visit-reason-proposal-{{ $index }}"
                                type="text"
                                x-model="proposals[{{ $index }}].summary"
                                value="{{ $proposal['summary'] }}"
                                class="w-full rounded-sm border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-900"
                            >
                        </div>
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.visit-reason.concerns.accept', $repairOrder) }}"
                            class="shrink-0"
                            data-refresh-scope="worksheet"
                            @submit.prevent="submitWorksheetForm($event)"
                        >
                            @csrf
                            <input type="hidden" name="opened_estimate_version" value="{{ $estimateVersion }}">
                            <input type="hidden" name="proposals[0][summary]" :value="proposals[{{ $index }}].summary" value="{{ $proposal['summary'] }}">
                            <input type="hidden" name="proposals[0][scope_entry_kind]" value="{{ $proposal['scope_entry_kind'] }}">
                            <button type="submit" class="min-h-8 rounded-sm bg-slate-950 px-2.5 text-xs font-semibold text-white hover:bg-slate-800">
                                Accept
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.visit-reason.concerns.accept', $repairOrder) }}"
                    data-refresh-scope="worksheet"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="opened_estimate_version" value="{{ $estimateVersion }}">
                    @foreach ($visitReasonProposals as $index => $proposal)
                        <input type="hidden" name="proposals[{{ $index }}][summary]" :value="proposals[{{ $index }}].summary" value="{{ $proposal['summary'] }}">
                        <input type="hidden" name="proposals[{{ $index }}][scope_entry_kind]" value="{{ $proposal['scope_entry_kind'] }}">
                    @endforeach
                    <button type="submit" class="min-h-8 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                        Accept all
                    </button>
                </form>
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.visit-reason.concerns.dismiss', $repairOrder) }}"
                    data-refresh-scope="worksheet"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <button type="submit" class="min-h-8 px-2 text-xs font-semibold text-slate-500 hover:text-slate-800">
                        Dismiss
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
