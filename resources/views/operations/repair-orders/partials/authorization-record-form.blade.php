@php
    use App\Ark\Operations\Approvals\ApprovalSource;
    use App\Ark\Operations\Financial\EstimateTotalsCalculator;
    use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

    $hasApprovedScope = $repairOrder->concerns->contains(
        fn ($concern) => $concern->disposition === RepairOrderConcernDisposition::Approved,
    );
    $hasRecommendedScope = $repairOrder->concerns->contains(
        fn ($concern) => $concern->disposition === RepairOrderConcernDisposition::Recommended,
    );
    $includesRecommendedWork = ! $hasApprovedScope && $hasRecommendedScope;
    $workAuthorization = app(App\Ark\Operations\RepairOrders\WorkCompletionAuthorization::class);
    $includesChangedWork = $hasApprovedScope
        && $workAuthorization->approvedConcernIdsChangedSinceApproval($repairOrder) !== [];
    $defaultTotals = $includesRecommendedWork
        ? app(EstimateTotalsCalculator::class)->recommendedTotalsForRead($repairOrder)
        : ($approvedTotals ?? $totals);
    $defaultApprovedCents = $defaultTotals->totalCents()
        + ($includesChangedWork ? $workAuthorization->workOutsideApprovedSalesCents($repairOrder) : 0);
    $defaultApprovedAmount = number_format($defaultApprovedCents / 100, 2, '.', '');
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @unless ($isTerminal ?? false)
        <div class="card-body" data-authorization-record>
            <div class="subheader">Record approval</div>
            <p class="small text-secondary mb-3">
                @if ($includesRecommendedWork)
                    Records the customer's approval of the recommended work on this estimate. Deferred and declined work is not included. Work added after this stays unauthorized.
                @elseif ($includesChangedWork)
                    Records the customer's approval of the work that needs authorization on approved scopes. Earlier approvals stay on file. Recommended, deferred, and declined work is not added.
                @elseif ($hasApprovedScope)
                    Records the scopes already marked approved. This does not add recommended, deferred, or declined work.
                @else
                    There is no recommended work to authorize. Deferred and declined work stays as it is.
                @endif
            </p>

            <form
                method="POST"
                action="{{ route('operations.repair-orders.authorization.store', $repairOrder) }}"
                data-worksheet-continuity
                data-refresh-scope="auth"
                data-saving-label="Recording authorization…"
                data-continuity-focus="#financial-rail button[type='submit']"
                @submit.prevent="window.arkWorksheetFormSubmit($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                @if ($includesChangedWork)
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD }}" value="{{ $workAuthorization->pendingChangesFingerprint($repairOrder) }}">
                @endif

                <div class="mb-3">
                    <label class="form-label" for="authorization-source-{{ $repairOrder->repair_order_id }}">Method</label>
                    <select id="authorization-source-{{ $repairOrder->repair_order_id }}" name="source" required class="form-select">
                        @foreach (ApprovalSource::cases() as $source)
                            <option value="{{ $source->value }}" @selected(old('source', ApprovalSource::InPerson->value) === $source->value)>{{ $source->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="authorization-approved-by-{{ $repairOrder->repair_order_id }}">Approved by</label>
                    <input
                        id="authorization-approved-by-{{ $repairOrder->repair_order_id }}"
                        type="text"
                        name="approved_by"
                        value="{{ old('approved_by', $repairOrder->customer->name) }}"
                        required
                        class="form-control"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="authorization-approved-amount-{{ $repairOrder->repair_order_id }}">Authorized amount</label>
                    <input
                        id="authorization-approved-amount-{{ $repairOrder->repair_order_id }}"
                        type="text"
                        name="approved_amount"
                        value="{{ old('approved_amount', $defaultApprovedAmount) }}"
                        inputmode="decimal"
                        class="form-control ark-money"
                        placeholder="0.00"
                    >
                    <div class="form-hint">
                        @if ($includesRecommendedWork)
                            Defaults to the recommended work on this estimate.
                        @else
                            Defaults to approved scope total when left blank.
                        @endif
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="authorization-notes-{{ $repairOrder->repair_order_id }}">Notes</label>
                    <textarea id="authorization-notes-{{ $repairOrder->repair_order_id }}" name="notes" rows="2" class="form-control" placeholder="Optional authorization notes">{{ old('notes') }}</textarea>
                </div>

                @error('authorization')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn btn-primary w-100">
                    Record approval
                </button>
            </form>
        </div>
    @endunless
@endcan
