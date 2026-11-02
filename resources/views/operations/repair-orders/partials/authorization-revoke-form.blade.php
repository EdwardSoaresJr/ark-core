@php
    use App\Ark\Operations\Approvals\ApprovalSource;
    use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

    $approvedConcerns = $repairOrder->concerns
        ->filter(fn ($concern) => $concern->disposition === RepairOrderConcernDisposition::Approved)
        ->values();
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @unless ($isTerminal ?? false)
        @if ($approvedConcerns->isNotEmpty())
            <details class="card card-sm mt-2" data-authorization-revoke>
                <summary class="card-body py-2 small fw-bold text-orange">
                    Revoke authorization
                </summary>
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.authorization.revoke', [$repairOrder, $approvalEvent]) }}"
                    data-worksheet-continuity
                    data-refresh-scope="auth"
                    data-saving-label="Revoking authorization…"
                    @submit.prevent="window.arkWorksheetFormSubmit($event)"
                    class="card-body border-top"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <p class="small text-secondary">Customer changed their mind or the authorization was recorded incorrectly. Reverts selected approved scopes and records the revocation.</p>

                    <fieldset class="mb-3">
                        <legend class="form-label">Scopes to revoke</legend>
                        @foreach ($approvedConcerns as $concern)
                            <label class="form-check">
                                <input
                                    type="checkbox"
                                    name="concern_ids[]"
                                    value="{{ $concern->id }}"
                                    checked
                                    class="form-check-input"
                                >
                                <span class="form-check-label">{{ $concern->summary }}</span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="mb-3">
                        <label class="form-label" for="authorization-revert-{{ $approvalEvent->id }}">Return scopes to</label>
                        <select id="authorization-revert-{{ $approvalEvent->id }}" name="revert_disposition" required class="form-select">
                            <option value="{{ RepairOrderConcernDisposition::Recommended->value }}" @selected(old('revert_disposition', RepairOrderConcernDisposition::Recommended->value) === RepairOrderConcernDisposition::Recommended->value)>Recommended (pending again)</option>
                            <option value="{{ RepairOrderConcernDisposition::Deferred->value }}" @selected(old('revert_disposition') === RepairOrderConcernDisposition::Deferred->value)>Deferred</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="authorization-revoke-source-{{ $approvalEvent->id }}">Method</label>
                        <select id="authorization-revoke-source-{{ $approvalEvent->id }}" name="source" required class="form-select">
                            @foreach (ApprovalSource::cases() as $source)
                                <option value="{{ $source->value }}" @selected(old('source', ApprovalSource::Phone->value) === $source->value)>{{ $source->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="authorization-revoked-by-{{ $approvalEvent->id }}">Revoked by</label>
                        <input
                            id="authorization-revoked-by-{{ $approvalEvent->id }}"
                            type="text"
                            name="revoked_by"
                            value="{{ old('revoked_by', $repairOrder->customer->name) }}"
                            required
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="authorization-revoke-notes-{{ $approvalEvent->id }}">Notes</label>
                        <textarea id="authorization-revoke-notes-{{ $approvalEvent->id }}" name="notes" rows="2" class="form-control" placeholder="Optional revocation notes">{{ old('notes') }}</textarea>
                    </div>

                    @error('authorization')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="btn btn-outline-warning w-100">
                        Revoke this authorization
                    </button>
                </form>
            </details>
        @else
            <p class="small text-secondary mt-2 mb-0">No approved scopes remain to revoke for this authorization.</p>
        @endif
    @endunless
@endcan
