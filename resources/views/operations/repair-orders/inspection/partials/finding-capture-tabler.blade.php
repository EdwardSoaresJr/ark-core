@php
    $tabletMode = (bool) ($tabletMode ?? false);
    $selectedConcernId = old('repair_order_concern_id', request()->integer('concern') ?: null);
    $defaultIntent = old('intent', session('finding_intent', $finding_intents[0]->value));
    $openCapture = $canEdit && (old('intent') !== null || request()->boolean('capture'));
@endphp

<div
    data-inspection-capture
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
            class="btn btn-primary btn-lg w-100"
            data-inspection-finding-btn
            x-ref="primaryBtn"
            x-on:click="openCapture()"
        >
            <x-tabler.icon name="plus" />
            Add Finding
        </button>
    @endif

    <div x-show="open" x-cloak x-transition.opacity>
        <div class="modal-backdrop show" x-on:click="closeCapture()"></div>
        <div
            class="modal d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="finding-capture-title"
            x-on:click.self="closeCapture()"
            data-inspection-capture-sheet
        >
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
                <form
                    method="post"
                    action="{{ route('operations.repair-orders.inspection.findings.store', $repairOrder) }}"
                    enctype="multipart/form-data"
                    class="modal-content"
                >
                    <div class="modal-header">
                        <h2 id="finding-capture-title" class="modal-title">Record finding</h2>
                        <button type="button" class="btn-close" aria-label="Close" x-on:click="closeCapture()"></button>
                    </div>

                    <div class="modal-body">
                        @csrf

                        <input type="hidden" name="intent" x-model="intent">

                        <fieldset class="mb-2">
                            <legend class="visually-hidden">Finding intent</legend>
                            <div class="btn-list">
                                @foreach ($finding_intents as $findingIntent)
                                    <button
                                        type="button"
                                        class="btn"
                                        x-bind:class="intent === @js($findingIntent->value) ? 'btn-primary' : ''"
                                        x-bind:aria-pressed="intent === @js($findingIntent->value) ? 'true' : 'false'"
                                        x-on:click="intent = @js($findingIntent->value)"
                                    >{{ $findingIntent->label() }}</button>
                                @endforeach
                            </div>
                        </fieldset>

                        <p class="form-hint mb-3">
                            Finding intent describes the condition - not part source, OEM/aftermarket type, or warranty. Use part line fields when selling parts.
                        </p>

                        <div class="mb-3">
                            <label class="form-label" for="finding-capture-label">What did you find?</label>
                            <input
                                type="text"
                                id="finding-capture-label"
                                name="label"
                                x-ref="titleInput"
                                value="{{ old('label') }}"
                                required
                                maxlength="191"
                                placeholder="Front brake pads, battery leak, tire tread…"
                                class="form-control form-control-lg"
                                autocomplete="off"
                            >
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col">
                                <label class="form-label" for="finding-capture-measurement">Measurement</label>
                                <input
                                    type="text"
                                    id="finding-capture-measurement"
                                    name="measurement_value"
                                    value="{{ old('measurement_value') }}"
                                    maxlength="64"
                                    placeholder="3"
                                    inputmode="decimal"
                                    class="form-control"
                                >
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="finding-capture-unit">Unit</label>
                                <input
                                    type="text"
                                    id="finding-capture-unit"
                                    name="measurement_unit"
                                    value="{{ old('measurement_unit', 'mm') }}"
                                    maxlength="32"
                                    placeholder="mm"
                                    class="form-control"
                                >
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="finding-capture-photo">Photo or video</label>
                            <input
                                type="file"
                                id="finding-capture-photo"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
                                capture="environment"
                                class="form-control"
                            >
                            <div class="form-hint">Camera strongly encouraged - photo or short video of the condition.</div>
                        </div>

                        <div class="mb-3">
                            <button
                                type="button"
                                class="btn btn-ghost-primary"
                                x-show="! showNote"
                                x-on:click="showNote = true; $nextTick(() => $refs.noteInput?.focus())"
                            >+ Add note (optional)</button>

                            <div x-show="showNote" x-cloak>
                                <label class="form-label" for="finding-capture-notes">Note</label>
                                <textarea
                                    id="finding-capture-notes"
                                    name="notes"
                                    x-ref="noteInput"
                                    rows="2"
                                    maxlength="5000"
                                    placeholder="Location, severity, customer context…"
                                    class="form-control"
                                >{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        @if ($concerns->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label" for="finding-capture-concern">Link to concern (optional)</label>
                                <select id="finding-capture-concern" name="repair_order_concern_id" class="form-select">
                                    <option value="">None</option>
                                    @foreach ($concerns as $concern)
                                        <option value="{{ $concern->id }}" @selected((string) $selectedConcernId === (string) $concern->id)>{{ $concern->summary }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger mb-0" role="alert">
                                <ul class="mb-0 ps-3" style="list-style: disc">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost-secondary" x-on:click="closeCapture()">Cancel</button>
                        <button type="submit" name="add_another" value="1" class="btn">Save &amp; add another</button>
                        <button type="submit" class="btn btn-primary">Save finding</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($canEdit)
        {{-- On the tablet surface the FAB sits above the walk's sticky Prev/Next bar, and from md up the bay layout has room for the inline button. --}}
        <button
            type="button"
            @class([
                'btn btn-primary btn-lg position-fixed end-0 m-3 shadow z-3',
                'bottom-0' => ! $tabletMode,
                'd-md-none' => $tabletMode,
            ])
            @if ($tabletMode) style="bottom: 3.5rem" @endif
            x-show="showFab && ! open"
            x-cloak
            x-on:click="openCapture()"
            aria-label="Add finding"
            data-inspection-finding-fab
        >
            <x-tabler.icon name="plus" />
            Add Finding
        </button>
    @endif
</div>
