@php
    $selectedConcernId = old('repair_order_concern_id', request()->integer('concern') ?: null);
    $defaultIntent = old('intent', session('finding_intent', $finding_intents[0]->value));
    $openCapture = $canEdit && (old('intent') !== null || request()->boolean('capture'));
@endphp

<div
    class="ops-inspection-capture"
    x-data="{
        open: @js($openCapture),
        intent: @js($defaultIntent),
        showNote: @js(filled(old('notes'))),
        showFab: false,
        init() {
            if (! this.$refs.primaryBtn) {
                return;
            }

            const observer = new IntersectionObserver(([entry]) => {
                this.showFab = ! entry.isIntersecting && ! this.open;
            }, { threshold: 0.15 });

            observer.observe(this.$refs.primaryBtn);

            this.$watch('open', (value) => {
                if (value) {
                    this.showFab = false;
                }
            });
        },
        openCapture() {
            this.open = true;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.titleInput?.focus());
        },
        closeCapture() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');

            const url = new URL(window.location.href);

            if (url.searchParams.has('capture') || url.searchParams.has('concern')) {
                url.searchParams.delete('capture');
                url.searchParams.delete('concern');
                const query = url.searchParams.toString();
                window.history.replaceState({}, '', url.pathname + (query ? `?${query}` : '') + url.hash);
            }
        },
    }"
    x-on:ark-open-finding-capture.window="openCapture()"
    x-on:keydown.escape.window="if (open) closeCapture()"
    @if ($canEdit)
        x-on:keydown.n.window="
            if (open || $event.target.closest('input, textarea, select, [contenteditable=true]')) return;
            $event.preventDefault();
            openCapture();
        "
    @endif
>
    @if ($canEdit)
        <button
            type="button"
            class="ops-inspection-finding-btn"
            x-ref="primaryBtn"
            x-on:click="openCapture()"
        >
            <span class="ops-inspection-finding-btn__icon" aria-hidden="true">+</span>
            <span class="ops-inspection-finding-btn__label">Add Finding</span>
        </button>
    @endif

    <div
        class="ops-inspection-capture__backdrop"
        x-show="open"
        x-cloak
        x-transition.opacity
        x-on:click="closeCapture()"
    ></div>

    <div
        class="ops-inspection-capture__sheet"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        role="dialog"
        aria-modal="true"
        aria-labelledby="finding-capture-title"
    >
        <div class="ops-inspection-capture__head">
            <h2 id="finding-capture-title" class="ops-inspection-capture__title">Record finding</h2>
            <button type="button" class="ops-inspection-capture__close" x-on:click="closeCapture()">Close</button>
        </div>

        <form
            method="post"
            action="{{ route('operations.repair-orders.inspection.findings.store', $repairOrder) }}"
            enctype="multipart/form-data"
            class="ops-inspection-capture__form"
        >
            @csrf

            <input type="hidden" name="intent" x-model="intent">

            <fieldset class="ops-inspection-intent-grid">
                <legend class="sr-only">Finding intent</legend>
                @foreach ($finding_intents as $findingIntent)
                    <button
                        type="button"
                        class="ops-inspection-intent-pill"
                        x-bind:class="intent === @js($findingIntent->value) ? 'ops-inspection-intent-pill--active' : ''"
                        x-on:click="intent = @js($findingIntent->value)"
                    >{{ $findingIntent->label() }}</button>
                @endforeach
            </fieldset>

            <p class="text-xs leading-5 text-slate-500">
                Finding intent describes the condition — not part source, OEM/aftermarket type, or warranty. Use part line fields when selling parts.
            </p>

            <label class="ops-inspection-field">
                <span class="ops-inspection-field__label">What did you find?</span>
                <input
                    type="text"
                    name="label"
                    x-ref="titleInput"
                    value="{{ old('label') }}"
                    required
                    maxlength="191"
                    placeholder="Front brake pads, battery leak, tire tread…"
                    class="ops-inspection-field__input ops-inspection-field__input--hero"
                    autocomplete="off"
                >
            </label>

            <div class="ops-inspection-measure-row">
                <label class="ops-inspection-field ops-inspection-field--grow">
                    <span class="ops-inspection-field__label">Measurement</span>
                    <input
                        type="text"
                        name="measurement_value"
                        value="{{ old('measurement_value') }}"
                        maxlength="64"
                        placeholder="3"
                        inputmode="decimal"
                        class="ops-inspection-field__input"
                    >
                </label>
                <label class="ops-inspection-field ops-inspection-field--unit">
                    <span class="ops-inspection-field__label">Unit</span>
                    <input
                        type="text"
                        name="measurement_unit"
                        value="{{ old('measurement_unit', 'mm') }}"
                        maxlength="32"
                        placeholder="mm"
                        class="ops-inspection-field__input"
                    >
                </label>
            </div>

            <label class="ops-inspection-photo-field">
                <span class="ops-inspection-field__label">Photo or video</span>
                <input
                    type="file"
                    name="photo"
                    accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
                    capture="environment"
                    class="ops-inspection-photo-field__input"
                >
                <span class="ops-inspection-photo-field__hint">Camera strongly encouraged — photo or short video of the condition.</span>
            </label>

            <div>
                <button
                    type="button"
                    class="ops-inspection-link-btn"
                    x-show="! showNote"
                    x-on:click="showNote = true; $nextTick(() => $refs.noteInput?.focus())"
                >+ Add note (optional)</button>

                <label class="ops-inspection-field" x-show="showNote" x-cloak>
                    <span class="ops-inspection-field__label">Note</span>
                    <textarea
                        name="notes"
                        x-ref="noteInput"
                        rows="2"
                        maxlength="5000"
                        placeholder="Location, severity, customer context…"
                        class="ops-inspection-field__input"
                    >{{ old('notes') }}</textarea>
                </label>
            </div>

            @if ($concerns->isNotEmpty())
                <label class="ops-inspection-field">
                    <span class="ops-inspection-field__label">Link to concern (optional)</span>
                    <select name="repair_order_concern_id" class="ops-inspection-field__input">
                        <option value="">None</option>
                        @foreach ($concerns as $concern)
                            <option value="{{ $concern->id }}" @selected((string) $selectedConcernId === (string) $concern->id)>{{ $concern->summary }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($errors->any())
                <ul class="ops-inspection-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="ops-inspection-capture__actions">
                <button type="button" class="ops-inspection-btn ops-inspection-btn--ghost" x-on:click="closeCapture()">Cancel</button>
                <button type="submit" name="add_another" value="1" class="ops-inspection-btn ops-inspection-btn--secondary">Save &amp; add another</button>
                <button type="submit" class="ops-inspection-btn ops-inspection-btn--primary">Save finding</button>
            </div>
        </form>
    </div>

    @if ($canEdit)
        <button
            type="button"
            class="ops-inspection-fab"
            x-show="showFab && ! open"
            x-cloak
            x-on:click="openCapture()"
            aria-label="Add finding"
        >
            <span aria-hidden="true">+</span> Add Finding
        </button>
    @endif
</div>
