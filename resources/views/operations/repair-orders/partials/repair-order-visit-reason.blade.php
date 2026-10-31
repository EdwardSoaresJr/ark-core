{{-- Reason for Visit - presentation only; author via workspace modal --}}
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
<div id="visit-reason" class="card card-sm mb-3 scroll-mt-6">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-fill" style="min-width: 0">
                <h2 class="subheader mb-1">Reason for Visit</h2>
                @if ($visitReasonText !== '')
                    <p class="mb-0" style="white-space: pre-line">{!! \App\Ark\Operations\RepairOrders\RepairOrderMention::html($visitReasonText, $priorVisitMentions['href_by_number'] ?? []) !!}</p>
                @else
                    <p class="mb-0 text-secondary fst-italic">Not recorded yet.</p>
                @endif
            </div>
            @if ($visitReasonEditable)
                <div class="btn-list flex-nowrap">
                    @if ($visitReasonText !== '')
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost-secondary"
                            @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor-visit-reason', context: {}, invokeEl: $event.currentTarget } }))"
                        >
                            Rewrite
                        </button>
                    @endif
                    <button
                        type="button"
                        class="btn btn-sm"
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
            <div class="mt-3 pt-3 border-top" x-data="{ proposals: @js($visitReasonProposalState) }">
                <h3 class="subheader mb-1">Suggested concerns</h3>
                <p class="small text-secondary mb-2">From the reason for visit. Accept to create concerns - the visit reason stays unchanged.</p>
                <div class="list-group mb-2">
                    @foreach ($visitReasonProposals as $index => $proposal)
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-2 py-2">
                            <div class="flex-fill" style="min-width: 0">
                                <label class="visually-hidden" for="visit-reason-proposal-{{ $index }}">Suggested concern {{ $index + 1 }}</label>
                                <input
                                    id="visit-reason-proposal-{{ $index }}"
                                    type="text"
                                    x-model="proposals[{{ $index }}].summary"
                                    value="{{ $proposal['summary'] }}"
                                    class="form-control form-control-sm"
                                >
                            </div>
                            <form
                                method="POST"
                                action="{{ route('operations.repair-orders.visit-reason.concerns.accept', $repairOrder) }}"
                                data-refresh-scope="worksheet"
                                @submit.prevent="submitWorksheetForm($event)"
                            >
                                @csrf
                                <input type="hidden" name="opened_estimate_version" value="{{ $estimateVersion }}">
                                <input type="hidden" name="proposals[0][summary]" :value="proposals[{{ $index }}].summary" value="{{ $proposal['summary'] }}">
                                <input type="hidden" name="proposals[0][scope_entry_kind]" value="{{ $proposal['scope_entry_kind'] }}">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Accept
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <div class="btn-list">
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
                        <button type="submit" class="btn btn-sm">
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
                        <button type="submit" class="btn btn-sm btn-ghost-secondary">
                            Dismiss
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
