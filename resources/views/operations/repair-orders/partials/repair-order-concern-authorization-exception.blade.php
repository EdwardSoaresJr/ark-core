@php
    $exceptionSurface = $exceptionSurface ?? 'dialog';
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

@if ($exceptionSurface === 'menu' && $canRecordException)
    <details
        class="ops-scope-more"
        x-data
        @click.outside="$el.open = false"
        @keydown.escape.prevent="$el.open = false"
    >
        <summary class="ops-scope-more__trigger" aria-label="More actions" aria-haspopup="menu">More</summary>
        <div class="ops-scope-more__panel" role="menu">
            <button
                type="button"
                class="ops-scope-more__item"
                role="menuitem"
                data-authorization-exception-action="record"
                aria-haspopup="dialog"
                @click="
                    const menu = $el.closest('details');
                    window.dispatchEvent(new CustomEvent('ark-authorization-exception-open', {
                        detail: {
                            concernId: {{ $concern->id }},
                            mode: 'record',
                            invokeEl: menu?.querySelector('summary') ?? $el,
                        },
                    }));
                    if (menu) {
                        menu.open = false;
                    }
                "
            >
                Record exception
            </button>
        </div>
    </details>
@endif

@if ($exceptionSurface === 'dialog' && ($canRecordException || $recordedExceptions->isNotEmpty()))
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
                class="ops-workspace-modal"
                role="dialog"
                aria-modal="true"
                data-authorization-exception-dialog="{{ $concern->id }}"
                @if ($reopenException) data-authorization-exception-reopen="{{ $concern->id }}" @endif
                :aria-labelledby="mode === 'inspect'
                    ? 'authorization-exception-inspect-title-{{ $concern->id }}'
                    : 'authorization-exception-record-title-{{ $concern->id }}'"
                @keydown.escape.window="onEscape($event)"
                @keydown.tab="trapFocus($event)"
            >
                <button type="button" class="ops-workspace-modal__backdrop" aria-label="Close" tabindex="-1" @click="close()"></button>
                <div
                    class="ops-workspace-modal__dialog ops-workspace-modal__dialog--exception"
                    x-ref="dialog"
                    tabindex="-1"
                    x-show="open"
                    x-transition:enter="ops-workspace-modal--enter"
                    x-transition:enter-start="ops-workspace-modal--enter-start"
                    x-transition:enter-end="ops-workspace-modal--enter-end"
                    x-transition:leave="ops-workspace-modal--leave"
                    x-transition:leave-start="ops-workspace-modal--leave-start"
                    x-transition:leave-end="ops-workspace-modal--leave-end"
                    @click.stop
                >
                    <header class="ops-workspace-modal__header">
                        <div class="ops-workspace-modal__heading min-w-0">
                            @if ($canRecordException)
                                <h2
                                    id="authorization-exception-record-title-{{ $concern->id }}"
                                    class="ops-workspace-modal__title"
                                    x-show="mode === 'record'"
                                >
                                    Record work exception
                                </h2>
                                <p class="ops-workspace-modal__helper" x-show="mode === 'record'" id="authorization-exception-record-help-{{ $concern->id }}">
                                    This documents an internal basis for proceeding. It does not record customer approval.
                                </p>
                            @endif
                            @if ($recordedExceptions->isNotEmpty())
                                <h2
                                    id="authorization-exception-inspect-title-{{ $concern->id }}"
                                    class="ops-workspace-modal__title"
                                    x-show="mode === 'inspect'"
                                    x-cloak
                                >
                                    Work exception
                                </h2>
                            @endif
                        </div>
                        <button type="button" class="ops-workspace-modal__close" @click="close()">Close</button>
                    </header>

                    @if ($canRecordException)
                        <form
                            x-show="mode === 'record'"
                            method="POST"
                            action="{{ route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]) }}"
                            @submit="submitRecord($event)"
                        >
                            @csrf
                            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                            <input type="hidden" name="authorization_exception_concern_id" value="{{ $concern->id }}">
                            <div class="ops-workspace-modal__body">
                                <div class="ops-exception-form">
                                    <label class="ops-exception-form__label" for="authorization-exception-reason-{{ $concern->id }}">Basis</label>
                                    <select
                                        id="authorization-exception-reason-{{ $concern->id }}"
                                        name="reason"
                                        class="ops-exception-form__control"
                                        required
                                        @if ($reopenException && $errors->has('reason')) aria-invalid="true" @endif
                                    >
                                        <option value="" @selected($selectedReason === '')>Select a basis</option>
                                        @foreach (App\Ark\Operations\RepairOrders\AuthorizationExceptionReason::cases() as $reason)
                                            <option value="{{ $reason->value }}" @selected($selectedReason === $reason->value)>{{ $reason->label() }}</option>
                                        @endforeach
                                    </select>

                                    <fieldset class="ops-exception-form__lines">
                                        <legend class="ops-exception-form__label">Work this covers</legend>
                                        <ul class="ops-exception-lines">
                                            @foreach ($exceptionLines as $line)
                                                @php
                                                    $lineQuantity = (float) $line->quantity;
                                                    $lineQuantityLabel = abs($lineQuantity - 1.0) > 0.001
                                                        ? 'Qty '.rtrim(rtrim(number_format($lineQuantity, 2, '.', ''), '0'), '.')
                                                        : null;
                                                @endphp
                                                <li class="ops-exception-lines__item">
                                                    <label class="ops-exception-lines__label">
                                                        <input type="checkbox" name="line_ids[]" value="{{ $line->id }}" @checked(in_array((string) $line->id, $selectedLineIds, true))>
                                                        <span class="ops-exception-lines__copy">
                                                            <span class="ops-exception-lines__kind">
                                                                {{ $line->type->staffLabel() }}
                                                                @if ($lineQuantityLabel)
                                                                    · {{ $lineQuantityLabel }}
                                                                @endif
                                                            </span>
                                                            <span class="ops-exception-lines__desc">{{ $line->description }}</span>
                                                        </span>
                                                        <span class="ops-exception-lines__amount">{{ $totals->format((int) $line->total_cents) }}</span>
                                                    </label>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </fieldset>

                                    <label class="ops-exception-form__label" for="authorization-exception-note-{{ $concern->id }}">What this covers and why</label>
                                    <textarea
                                        id="authorization-exception-note-{{ $concern->id }}"
                                        name="note"
                                        class="ops-exception-form__control ops-exception-form__note"
                                        required
                                        minlength="{{ App\Ark\Operations\RepairOrders\AuthorizationExceptionNote::MIN_LENGTH }}"
                                        maxlength="2000"
                                        rows="4"
                                        aria-describedby="authorization-exception-note-hint-{{ $concern->id }}"
                                        @if ($reopenException && $errors->has('note')) aria-invalid="true" @endif
                                    >{{ $exceptionNote }}</textarea>
                                    <p class="ops-exception-form__hint" id="authorization-exception-note-hint-{{ $concern->id }}">Name the work this covers and why this basis applies.</p>
                                </div>
                            </div>
                            <p class="ops-workspace-modal__validation" role="alert" x-show="lineError" x-cloak>
                                Select the labor or parts this exception covers.
                            </p>
                            @if ($reopenException)
                                <div class="ops-workspace-modal__validation" role="alert">
                                    @foreach (['reason', 'line_ids', 'note'] as $field)
                                        @error($field)
                                            <p>{{ $message }}</p>
                                        @enderror
                                    @endforeach
                                </div>
                            @endif
                            <footer class="ops-workspace-modal__footer">
                                <div class="ops-workspace-modal__footer-end">
                                    <button type="button" class="ops-workspace-modal__cancel" @click="close()">Cancel</button>
                                    <button type="submit" class="ops-workspace-modal__primary" :disabled="submitting">Record exception</button>
                                </div>
                            </footer>
                        </form>
                    @endif

                    @if ($recordedExceptions->isNotEmpty())
                        <div x-show="mode === 'inspect'" @unless($canRecordException) x-cloak @endunless>
                            <div class="ops-workspace-modal__body">
                                @foreach ($recordedExceptions as $exception)
                                    <article
                                        class="ops-exception-record"
                                        data-authorization-exception-record="{{ $exception->id }}"
                                        x-show="showsException(@js($exception->lineIds()))"
                                    >
                                        <h3 class="ops-exception-record__basis">{{ $exception->reason->label() }}</h3>
                                        <p class="ops-exception-record__meta">
                                            @if ($exception->recordedBy?->name)
                                                Recorded by {{ $exception->recordedBy->name }}
                                            @else
                                                Recorded
                                            @endif
                                            @if ($exception->created_at)
                                                · {{ $exception->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                            @endif
                                        </p>
                                        <p class="ops-exception-record__note">{{ $exception->note }}</p>
                                        <p class="ops-exception-record__covers-label">Covered work</p>
                                        <ul class="ops-exception-record__covers">
                                            @foreach ($exception->lineIds() as $lineId)
                                                @php
                                                    $coveredLine = $concern->lines->firstWhere('id', $lineId);
                                                @endphp
                                                <li>
                                                    @if ($coveredLine)
                                                        <span>{{ $coveredLine->type->staffLabel() }} · {{ $coveredLine->description }}</span>
                                                        <span class="ops-exception-record__amount">{{ $totals->format((int) $coveredLine->total_cents) }}</span>
                                                    @else
                                                        <span>Removed from the estimate</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </article>
                                @endforeach
                            </div>
                            <footer class="ops-workspace-modal__footer">
                                <div class="ops-workspace-modal__footer-end">
                                    <button type="button" class="ops-workspace-modal__cancel" @click="close()">Close</button>
                                </div>
                            </footer>
                        </div>
                    @endif
                </div>
            </div>
        </template>
    </div>
@endif
