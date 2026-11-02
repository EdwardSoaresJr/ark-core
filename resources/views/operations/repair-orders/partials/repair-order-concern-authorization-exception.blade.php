@php
    $exceptionLines = $concern->linesEligibleForAuthorizationException();
    $canRecordException = ! ($isTerminal ?? false)
        && $exceptionLines->isNotEmpty()
        && App\Ark\Operations\RepairOrders\RecordAuthorizationExceptionAction::actorMayRecord(auth()->user());
    $recordedExceptions = $concern->relationLoaded('authorizationExceptions')
        ? $concern->authorizationExceptions
        : collect();
    $restoreException = (string) old('authorization_exception_concern_id') === (string) $concern->id;
    $selectedReason = $restoreException ? (string) old('reason', '') : '';
    $selectedLineIds = $restoreException
        ? array_map(static fn (mixed $id): string => (string) $id, (array) old('line_ids', []))
        : [];
    $exceptionNote = $restoreException ? (string) old('note', '') : '';
    $reopenException = $restoreException && $errors->any();
@endphp

@if ($canRecordException || $recordedExceptions->isNotEmpty())
    <div
        x-data="arkAuthorizationExceptionDialog(@js([
            'concernId' => $concern->id,
            'canRecord' => $canRecordException,
            'reopen' => $reopenException,
        ]))"
    >
        <template x-teleport="body">
            <div
                x-show="open"
                x-cloak
                data-authorization-exception-dialog="{{ $concern->id }}"
                @if ($reopenException) data-authorization-exception-reopen="{{ $concern->id }}" @endif
                @keydown.escape.window="onEscape($event)"
                @keydown.tab="trapFocus($event)"
            >
                <div class="modal-backdrop show"></div>
                <div
                    class="modal d-block"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="mode === 'inspect'
                        ? 'authorization-exception-inspect-title-{{ $concern->id }}'
                        : 'authorization-exception-record-title-{{ $concern->id }}'"
                    @click.self="close()"
                >
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down" @click.self="close()">
                        <div class="modal-content" x-ref="dialog" tabindex="-1">
                            <div class="modal-header">
                                <div class="overflow-hidden">
                                    @if ($canRecordException)
                                        <h2
                                            id="authorization-exception-record-title-{{ $concern->id }}"
                                            class="modal-title"
                                            x-show="mode === 'record'"
                                        >
                                            Record work exception
                                        </h2>
                                        <div class="small text-secondary" x-show="mode === 'record'" id="authorization-exception-record-help-{{ $concern->id }}">
                                            This documents an internal basis for proceeding. It does not record customer approval.
                                        </div>
                                    @endif
                                    @if ($recordedExceptions->isNotEmpty())
                                        <h2
                                            id="authorization-exception-inspect-title-{{ $concern->id }}"
                                            class="modal-title"
                                            x-show="mode === 'inspect'"
                                            x-cloak
                                        >
                                            Work exception
                                        </h2>
                                    @endif
                                </div>
                                <button type="button" class="btn-close" aria-label="Close" @click="close()"></button>
                            </div>

                            @if ($canRecordException)
                                <div class="modal-body" x-show="mode === 'record'">
                                    <form
                                        id="authorization-exception-form-{{ $concern->id }}"
                                        method="POST"
                                        action="{{ route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]) }}"
                                        @submit="submitRecord($event)"
                                    >
                                        @csrf
                                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                                        <input type="hidden" name="authorization_exception_concern_id" value="{{ $concern->id }}">
                                        <div class="mb-3">
                                            <label class="form-label" for="authorization-exception-reason-{{ $concern->id }}">Basis</label>
                                            <select
                                                id="authorization-exception-reason-{{ $concern->id }}"
                                                name="reason"
                                                @class(['form-select', 'is-invalid' => $reopenException && $errors->has('reason')])
                                                required
                                                @if ($reopenException && $errors->has('reason')) aria-invalid="true" @endif
                                            >
                                                <option value="" @selected($selectedReason === '')>Select a basis</option>
                                                @foreach (App\Ark\Operations\RepairOrders\AuthorizationExceptionReason::cases() as $reason)
                                                    <option value="{{ $reason->value }}" @selected($selectedReason === $reason->value)>{{ $reason->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <fieldset class="mb-3">
                                            <legend class="form-label">Work this covers</legend>
                                            <div class="list-group" data-authorization-exception-lines>
                                                @foreach ($exceptionLines as $line)
                                                    @php
                                                        $lineQuantity = (float) $line->quantity;
                                                        $lineQuantityLabel = abs($lineQuantity - 1.0) > 0.001
                                                            ? 'Qty '.rtrim(rtrim(number_format($lineQuantity, 2, '.', ''), '0'), '.')
                                                            : null;
                                                    @endphp
                                                    <label class="list-group-item d-flex align-items-start gap-2">
                                                        <input type="checkbox" class="form-check-input flex-shrink-0 mt-1" name="line_ids[]" value="{{ $line->id }}" @checked(in_array((string) $line->id, $selectedLineIds, true))>
                                                        <span class="flex-fill" style="min-width: 0">
                                                            <span class="d-block small text-secondary">
                                                                {{ $line->type->staffLabel() }}
                                                                @if ($lineQuantityLabel)
                                                                    · {{ $lineQuantityLabel }}
                                                                @endif
                                                            </span>
                                                            <span class="d-block">{{ $line->description }}</span>
                                                        </span>
                                                        <span class="flex-shrink-0 fw-medium ark-money">{{ $totals->format((int) $line->total_cents) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>

                                        <label class="form-label" for="authorization-exception-note-{{ $concern->id }}">What this covers and why</label>
                                        <textarea
                                            id="authorization-exception-note-{{ $concern->id }}"
                                            name="note"
                                            @class(['form-control', 'is-invalid' => $reopenException && $errors->has('note')])
                                            required
                                            minlength="{{ App\Ark\Operations\RepairOrders\AuthorizationExceptionNote::MIN_LENGTH }}"
                                            maxlength="2000"
                                            rows="4"
                                            aria-describedby="authorization-exception-note-hint-{{ $concern->id }}"
                                            @if ($reopenException && $errors->has('note')) aria-invalid="true" @endif
                                        >{{ $exceptionNote }}</textarea>
                                        <div class="form-hint" id="authorization-exception-note-hint-{{ $concern->id }}">Name the work this covers and why this basis applies.</div>
                                    </form>
                                </div>
                                <div class="modal-footer flex-wrap" x-show="mode === 'record'">
                                    <div class="alert alert-danger w-100 mb-2" role="alert" x-show="lineError" x-cloak>
                                        Select the labor or parts this exception covers.
                                    </div>
                                    @if ($reopenException)
                                        <div class="alert alert-danger w-100 mb-2" role="alert">
                                            @foreach (['reason', 'line_ids', 'note'] as $field)
                                                @error($field)
                                                    <div>{{ $message }}</div>
                                                @enderror
                                            @endforeach
                                        </div>
                                    @endif
                                    <button type="button" class="btn" @click="close()">Cancel</button>
                                    <button type="submit" form="authorization-exception-form-{{ $concern->id }}" class="btn btn-primary" :disabled="submitting">Record exception</button>
                                </div>
                            @endif

                            @if ($recordedExceptions->isNotEmpty())
                                <div class="modal-body" x-show="mode === 'inspect'" @unless($canRecordException) x-cloak @endunless>
                                    @foreach ($recordedExceptions as $exception)
                                        <article
                                            class="card card-sm mb-3"
                                            data-authorization-exception-record="{{ $exception->id }}"
                                            x-show="showsException(@js($exception->lineIds()))"
                                        >
                                            <div class="card-body">
                                                <h3 class="card-title mb-1" data-authorization-exception-basis>{{ $exception->reason->label() }}</h3>
                                                <div class="small text-secondary">
                                                    @if ($exception->recordedBy?->name)
                                                        Recorded by {{ $exception->recordedBy->name }}
                                                    @else
                                                        Recorded
                                                    @endif
                                                    @if ($exception->created_at)
                                                        · {{ $exception->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                                    @endif
                                                </div>
                                                <p class="mt-2" style="white-space: pre-wrap">{{ $exception->note }}</p>
                                                <div class="subheader mb-1">Covered work</div>
                                                <ul class="list-unstyled mb-0 small">
                                                    @foreach ($exception->lineIds() as $lineId)
                                                        @php
                                                            $coveredLine = $concern->lines->firstWhere('id', $lineId);
                                                        @endphp
                                                        <li class="d-flex justify-content-between gap-3">
                                                            @if ($coveredLine)
                                                                <span>{{ $coveredLine->type->staffLabel() }} · {{ $coveredLine->description }}</span>
                                                                <span class="flex-shrink-0 ark-money">{{ $totals->format((int) $coveredLine->total_cents) }}</span>
                                                            @else
                                                                <span>Removed from the estimate</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                                <div class="modal-footer" x-show="mode === 'inspect'" @unless($canRecordException) x-cloak @endunless>
                                    <button type="button" class="btn" @click="close()">Close</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
