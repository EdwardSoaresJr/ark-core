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

<section class="border-t border-slate-200 px-3 py-2.5">
    <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Commitments</p>
    <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Record what we promised the customer — owner and due date stay visible on Today.</p>

    @if ($openCommitments->isNotEmpty())
        <ul class="mt-2 divide-y divide-slate-100 border border-slate-200 bg-white">
            @foreach ($openCommitments as $commitment)
                <li class="flex items-start justify-between gap-3 px-3 py-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-950">{{ $commitment->type->label() }}</p>
                        <p class="mt-0.5 text-xs leading-4 text-slate-600">{{ $commitment->reason }}</p>
                        <p class="mt-0.5 text-[11px] font-medium text-slate-400">
                            Due {{ ShopDisplayTimezone::format($commitment->due_at, 'M j, g:i A') }}
                            · {{ $commitment->owner?->name ?? 'Unassigned' }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('operations.commitments.fulfill', $commitment) }}">
                        @csrf
                        <button type="submit" class="ops-btn ops-btn--secondary ops-btn--sm">Done</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    @if (! ($isTerminal ?? false))
        <form
            method="POST"
            action="{{ route('operations.repair-orders.commitments.store', $repairOrder) }}"
            class="mt-2 space-y-2 border border-slate-200 bg-slate-50 p-3"
        >
            @csrf
            <input type="hidden" name="type" value="{{ CommitmentType::CustomerUpdate->value }}">

            <label class="block">
                <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">What we promised</span>
                <textarea
                    name="reason"
                    rows="2"
                    required
                    maxlength="500"
                    placeholder="Warranty callback promised"
                    class="mt-1 w-full rounded border-slate-300 text-sm"
                >{{ old('reason') }}</textarea>
            </label>

            <div class="grid gap-2 sm:grid-cols-2">
                <label class="block">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">Due</span>
                    <input
                        type="datetime-local"
                        name="due_at"
                        required
                        value="{{ old('due_at', $defaultDueAt) }}"
                        class="mt-1 w-full rounded border-slate-300 text-sm"
                    >
                </label>

                <label class="block">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">Owner</span>
                    <select name="owner_user_id" required class="mt-1 w-full rounded border-slate-300 text-sm">
                        @foreach ($assignableOwners as $owner)
                            <option value="{{ $owner->id }}" @selected((int) old('owner_user_id', $defaultOwnerId) === $owner->id)>
                                {{ $owner->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <button type="submit" class="ops-btn ops-btn--primary ops-btn--sm">Record commitment</button>
        </form>
    @endif
</section>
