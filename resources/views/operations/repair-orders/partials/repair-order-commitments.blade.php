@php
    use App\Ark\Operations\Commitments\CommitmentAssignableOwners;
    use App\Ark\Operations\Commitments\CommitmentStatus;
    use App\Ark\Operations\Commitments\CommitmentType;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $assignableOwners = app(CommitmentAssignableOwners::class)->all();
    $defaultOwnerId = auth()->id();
    $defaultDueAt = ShopDisplayTimezone::now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
    $openCommitments = $repairOrder->operationalCommitments
        ->filter(fn ($commitment) => $commitment->status === CommitmentStatus::Open)
        ->sortBy(fn ($commitment) => $commitment->due_at?->getTimestamp() ?? 0)
        ->values();
@endphp

<section class="card card-sm" data-repair-order-commitments>
    <div class="card-header">
        <div>
            <h3 class="card-title">Commitments</h3>
            <p class="card-subtitle">Record what we promised the customer - owner and due date stay visible on Today.</p>
        </div>
    </div>

    @if ($openCommitments->isNotEmpty())
        <div class="list-group list-group-flush">
            @foreach ($openCommitments as $commitment)
                <div class="list-group-item d-flex align-items-start justify-content-between gap-3">
                    <div style="min-width: 0">
                        <div class="fw-medium">{{ $commitment->type->label() }}</div>
                        <div class="small text-secondary">{{ $commitment->reason }}</div>
                        <div class="small text-secondary">
                            Due {{ ShopDisplayTimezone::format($commitment->due_at, 'M j, g:i A') }}
                            · {{ $commitment->owner?->name ?? 'Unassigned' }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('operations.commitments.fulfill', $commitment) }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="btn btn-sm">Done</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    @if (! ($isTerminal ?? false))
        <form
            method="POST"
            action="{{ route('operations.repair-orders.commitments.store', $repairOrder) }}"
            class="card-body border-top"
        >
            @csrf
            <input type="hidden" name="type" value="{{ CommitmentType::CustomerUpdate->value }}">

            <div class="mb-3">
                <label class="form-label" for="commitment-reason-{{ $repairOrder->repair_order_id }}">What we promised</label>
                <textarea
                    id="commitment-reason-{{ $repairOrder->repair_order_id }}"
                    name="reason"
                    rows="2"
                    required
                    maxlength="500"
                    placeholder="Warranty callback promised"
                    class="form-control"
                >{{ old('reason') }}</textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="commitment-due-{{ $repairOrder->repair_order_id }}">Due</label>
                    <input
                        id="commitment-due-{{ $repairOrder->repair_order_id }}"
                        type="datetime-local"
                        name="due_at"
                        required
                        value="{{ old('due_at', $defaultDueAt) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label" for="commitment-owner-{{ $repairOrder->repair_order_id }}">Owner</label>
                    <select id="commitment-owner-{{ $repairOrder->repair_order_id }}" name="owner_user_id" required class="form-select">
                        @foreach ($assignableOwners as $owner)
                            <option value="{{ $owner->id }}" @selected((int) old('owner_user_id', $defaultOwnerId) === $owner->id)>
                                {{ $owner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Record commitment</button>
        </form>
    @endif
</section>
