@php
    /** @var \App\Ark\Operations\Today\TodayRecommendation $recommendation */
    use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
@endphp

<article class="ops-today-card">
    <header class="ops-today-card__head">
        <h3 class="ops-today-card__title">{{ $recommendation->title }}</h3>
        <a href="{{ $recommendation->repairOrderUrl }}" class="ops-today-card__ro">RO #{{ $recommendation->repairOrderId }}</a>
    </header>

    <div class="ops-today-card__body">
        <div @class([
            'ops-today-card__posture',
            'ops-today-card__posture--emphasis' => $recommendation->impactKind === \App\Ark\Operations\Today\TodayImpactKind::Revenue,
        ])>
            <p class="ops-today-card__impact">
                <span class="ops-today-card__impact-kind">{{ $recommendation->impactKind->label() }}</span>
                <span class="ops-today-card__impact-value">{{ $recommendation->impactLabel }}</span>
            </p>
            <p class="ops-today-card__next">{{ $recommendation->suggestedAction }}</p>
        </div>

        <div class="ops-today-card__signals">
            <div class="ops-today-card__signals-head">
                <p class="ops-today-card__signals-label">Why</p>
                <details class="ops-today-card__context">
                    <summary class="ops-today-card__context-trigger">Full context</summary>
                    <div class="ops-today-card__context-panel" role="region" aria-label="Full recommendation context">
                        <p class="ops-today-card__context-impact">{{ $recommendation->impactLabel }}</p>
                        <p class="ops-today-card__context-action">{{ $recommendation->suggestedAction }}</p>
                        <ul class="ops-today-card__context-list">
                            @foreach ($recommendation->whyReasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                </details>
            </div>
            <ul class="ops-today-card__signals-list">
                @foreach ($recommendation->whyReasons as $reason)
                    <li>
                        <span class="ops-today-card__signal" tabindex="0">
                            <span class="ops-today-card__signal-text">{{ $reason }}</span>
                            <span class="ops-today-card__signal-popover" role="tooltip">{{ $reason }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <footer class="ops-today-card__actions">
        <a href="{{ $recommendation->repairOrderUrl }}" class="ops-today-card__action-link ops-today-card__action-link--primary">Open RO</a>
        @if ($recommendation->textUrl !== null)
            <a href="{{ $recommendation->textUrl }}" class="ops-today-card__action-link">Text</a>
        @endif
        @if ($recommendation->callUrl !== null)
            <a href="{{ $recommendation->callUrl }}" class="ops-today-card__action-link">Call</a>
        @endif
        @if ($recommendation->canCloseLost)
            <details
                class="ops-today-card__close-lost"
                id="today-close-lost-{{ $recommendation->repairOrderId }}"
                @if ((int) old('repair_order_id') === $recommendation->repairOrderId && $errors->has('close_lost')) open @endif
            >
                <summary class="ops-today-card__action-link ops-today-card__close-lost-trigger">
                    <span>Close lost</span>
                    <svg class="ops-today-card__close-lost-chevron" aria-hidden="true" viewBox="0 0 20 20" fill="none">
                        <path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </summary>
                <form
                    method="POST"
                    action="{{ route('operations.today.close-lost') }}"
                    class="ops-today-card__close-lost-menu"
                    aria-label="Close repair order lost"
                >
                    @csrf
                    <input type="hidden" name="repair_order_id" value="{{ $recommendation->repairOrderId }}">
                    <p class="ops-today-card__close-lost-label">Why is this repair order closing lost?</p>
                    <label for="today-lost-reason-{{ $recommendation->repairOrderId }}" class="sr-only">Lost reason</label>
                    <select
                        id="today-lost-reason-{{ $recommendation->repairOrderId }}"
                        name="lost_reason_key"
                        required
                        class="ops-today-card__close-lost-select"
                    >
                        <option value="">Choose lost reason…</option>
                        @foreach (RepairOrderLostReason::options() as $option)
                            <option value="{{ $option['value'] }}" @selected(old('lost_reason_key') === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <label for="today-lost-note-{{ $recommendation->repairOrderId }}" class="sr-only">Lost reason note</label>
                    <input
                        id="today-lost-note-{{ $recommendation->repairOrderId }}"
                        type="text"
                        name="lost_reason_note"
                        value="{{ old('lost_reason_note') }}"
                        maxlength="500"
                        placeholder="Required when reason is Other"
                        class="ops-today-card__close-lost-note"
                    >
                    <button type="submit" class="ops-today-card__close-lost-submit">
                        Confirm close lost
                    </button>
                </form>
            </details>
        @endif
        <details class="ops-today-card__snooze" id="today-snooze-{{ $recommendation->repairOrderId }}">
            <summary class="ops-today-card__action-link ops-today-card__snooze-trigger">
                <span>Snooze</span>
                <svg class="ops-today-card__snooze-chevron" aria-hidden="true" viewBox="0 0 20 20" fill="none">
                    <path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </summary>
            <div class="ops-today-card__snooze-menu" role="menu" aria-label="Snooze duration">
                @foreach (\App\Ark\Operations\Today\TodayRecommendationSnoozeDuration::cases() as $duration)
                    <form method="POST" action="{{ route('operations.today.snooze') }}" role="none">
                        @csrf
                        <input type="hidden" name="repair_order_id" value="{{ $recommendation->repairOrderId }}">
                        <input type="hidden" name="duration" value="{{ $duration->value }}">
                        <button type="submit" class="ops-today-card__snooze-option" role="menuitem">
                            {{ $duration->label() }}
                        </button>
                    </form>
                @endforeach
            </div>
        </details>
    </footer>
</article>
