@php
    $popupMode = $popupMode ?? false;
    $hideSubmitActions = $hideSubmitActions ?? false;
    $workspaceModalForm = $workspaceModalForm ?? null;
    $intakeConfig = $intakeConfig ?? [];
@endphp

<form
    method="POST"
    action="{{ route('operations.repair-orders.concerns.store', $repairOrder) }}"
    id="concern-store-form"
    data-continuity-region="composer"
    data-worksheet-continuity
    data-refresh-scope="worksheet"
    data-continuity-focus="#concern-store [name='summary']"
    data-saving-label="Saving…"
    @if ($workspaceModalForm) data-workspace-modal-form="{{ $workspaceModalForm }}" @endif
    @submit="onFormSubmit()"
    @submit.prevent="submitWorksheetForm($event)"
    x-data="arkScopeEntryIntake(@js($intakeConfig))"
    x-init="init()"
    x-cloak
>
    @csrf
    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
    <input type="hidden" name="scope_entry_kind" :value="entryKind || ''">
    <input type="hidden" name="scope_entry_concept_id" :value="selectedConceptId || ''">
    <input type="hidden" name="observed_summary" :value="observedSummary || summary">

    @unless ($popupMode)
        <div class="mb-3">
            <h3 class="card-title mb-1">Add Concern</h3>
            <p class="text-secondary small mb-0">Organize the estimate into work items. Pick a match or enter something new.</p>
        </div>
    @endunless

    <div>
        @unless ($popupMode)
            <p class="form-hint mb-1" data-compose-save-clarity>
                Not saved until you create the concern. Refreshing now discards typed text.
            </p>
        @endunless
        <label class="visually-hidden" for="scope-entry-summary">Add concern</label>
        <div class="position-relative">
            <textarea
                id="scope-entry-summary"
                name="summary"
                required
                rows="3"
                autocomplete="off"
                spellcheck="true"
                maxlength="2000"
                @class(['form-control', 'is-invalid' => $errors->has('summary') || $errors->has('observed_summary')])
                placeholder="e.g. Front brakes grinding, Check engine light, Oil change"
                x-model="summary"
                x-ref="summaryInput"
                @keydown="handleKeydown($event)"
                @input.debounce.150ms="handleSummaryInput()"
                @focus="fetchSuggestions()"
                @error('summary') aria-invalid="true" @enderror
            >{{ old('summary') }}</textarea>
            <div
                class="dropdown-menu w-100 top-100 start-0 mt-1"
                :class="{ show: mentionOpen && mentionMatches.length > 0 }"
                role="listbox"
                aria-label="Previous visits"
                data-scope-entry-mentions
            >
                <template x-for="(row, index) in mentionMatches" :key="row.number">
                    <button
                        type="button"
                        class="dropdown-item flex-column align-items-start text-wrap"
                        :class="{ active: index === mentionActiveIndex }"
                        role="option"
                        @mousedown.prevent="choosePriorVisit(row)"
                    >
                        <span class="fw-medium" x-text="row.label"></span>
                        <span class="small text-secondary" x-text="row.detail"></span>
                    </button>
                </template>
            </div>
            <div
                class="dropdown-menu w-100 top-100 start-0 mt-1 overflow-auto"
                style="max-height: 20rem"
                :class="{ show: suggestionsOpen && hasMatches }"
                @click.outside="suggestionsOpen = false; activeSuggestionIndex = -1"
                role="listbox"
                aria-label="Shop Memory suggestions"
                data-scope-entry-suggestions
            >
                <template x-if="featured?.summary">
                    <div>
                        <h6 class="dropdown-header">Most common at your shop</h6>
                        <button
                            type="button"
                            class="dropdown-item text-wrap fw-medium"
                            :class="{ active: isSuggestionActive(featured.entry_kind, featured.summary) }"
                            @click="chooseSuggestion(featured.entry_kind, featured.summary, featured.concept_id, featured.suggestion_id, featured.provider)"
                        >
                            <span
                                class="me-1"
                                x-show="isSuggestionActive(featured.entry_kind, featured.summary)"
                                aria-hidden="true"
                            >✓</span>
                            <span x-text="featured.summary"></span>
                        </button>
                    </div>
                </template>

                <template x-for="group in suggestionGroups" :key="group.entry_kind">
                    <div>
                        <h6 class="dropdown-header" x-text="group.label"></h6>
                        <ul class="list-unstyled mb-0">
                            <template x-for="row in group.suggestions" :key="suggestionKey(group.entry_kind, typeof row === 'string' ? row : row.summary)">
                                <li role="option" :aria-selected="isSuggestionActive(group.entry_kind, typeof row === 'string' ? row : row.summary)">
                                    <button
                                        type="button"
                                        class="dropdown-item text-wrap"
                                        :class="{ active: isSuggestionActive(group.entry_kind, typeof row === 'string' ? row : row.summary) }"
                                        @click="chooseSuggestion(group.entry_kind, typeof row === 'string' ? row : row.summary, typeof row === 'object' ? row.concept_id : null, typeof row === 'object' ? row.suggestion_id : null, typeof row === 'object' ? row.provider : null)"
                                    >
                                        <span
                                            class="me-1"
                                            x-show="isSuggestionActive(group.entry_kind, typeof row === 'string' ? row : row.summary)"
                                            aria-hidden="true"
                                        >✓</span>
                                        <span x-text="typeof row === 'string' ? row : row.summary"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </div>
        @error('summary')
            <p class="invalid-feedback d-block" data-field-error data-concern-error>{{ $message }}</p>
        @enderror
        @error('observed_summary')
            <p class="invalid-feedback d-block" data-field-error data-concern-error>{{ $message }}</p>
        @enderror
        @include('operations.repair-orders.partials.repair-order-vehicle-memory', [
            'visitInsert' => 'insertPriorVisit',
        ])

        @unless ($hideSubmitActions)
            <div class="btn-list mt-3">
                <button type="submit" class="btn btn-primary">
                    Create Concern
                </button>
                <template x-if="aiRewriteEnabled">
                    <button
                        type="button"
                        class="btn"
                        @click="rewriteSummary()"
                        :disabled="! summary.trim() || rewriting"
                    >
                        <span x-text="rewriting ? 'Rewriting…' : 'Rewrite'"></span>
                    </button>
                </template>
            </div>
        @else
            <template x-if="aiRewriteEnabled">
                <div class="mt-3">
                    <button
                        type="button"
                        class="btn"
                        @click="rewriteSummary()"
                        :disabled="! summary.trim() || rewriting"
                    >
                        <span x-text="rewriting ? 'Rewriting…' : 'Rewrite'"></span>
                    </button>
                </div>
            </template>
        @endunless

        <p class="form-hint mt-2 mb-0">
            <span class="fw-medium">Keyboard:</span>
            <span x-show="hasMatches">Enter fills top match · ↑↓ choose · Esc dismiss · Shift+Enter new line · Create Concern saves</span>
            <span x-show="! hasMatches">Enter or Create Concern continues · Shift+Enter new line</span>
        </p>
    </div>
</form>
