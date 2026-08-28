@php
    $popupMode = $popupMode ?? false;
    $hideSubmitActions = $hideSubmitActions ?? false;
    $workspaceModalForm = $workspaceModalForm ?? null;
    $intakeConfig = $intakeConfig ?? [];
@endphp

<form
    method="POST"
    action="{{ route('operations.repair-orders.concerns.store', $repairOrder) }}"
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
        <div class="ops-worksheet-entry-intake__header">
            <p class="ops-worksheet-entry-intake__title">Add Concern</p>
            <p class="ops-worksheet-entry-intake__subtitle">Organize the estimate into work items. Pick a match or enter something new.</p>
        </div>
    @endunless

    <div class="ops-worksheet-entry-intake__compose">
        @unless ($popupMode)
            <p class="mb-1 text-[11px] font-medium text-slate-500" data-compose-save-clarity>
                Not saved until you create the concern. Refreshing now discards typed text.
            </p>
        @endunless
        <label class="sr-only" for="scope-entry-summary">Add concern</label>
        <div class="ops-worksheet-entry-intake__input-wrap">
            <textarea
                id="scope-entry-summary"
                name="summary"
                required
                rows="3"
                autocomplete="off"
                spellcheck="true"
                maxlength="2000"
                class="ops-field-input ops-worksheet-entry-intake__summary"
                placeholder="e.g. Front brakes grinding, Check engine light, Oil change"
                x-model="summary"
                x-ref="summaryInput"
                @keydown="handleKeydown($event)"
                @input.debounce.150ms="handleSummaryInput()"
                @focus="fetchSuggestions()"
                @error('summary') aria-invalid="true" @enderror
            >{{ old('summary') }}</textarea>
            @error('summary')
                <p class="ops-field-error mt-1 text-xs font-medium text-rose-700" data-concern-error>{{ $message }}</p>
            @enderror
            @error('observed_summary')
                <p class="ops-field-error mt-1 text-xs font-medium text-rose-700" data-concern-error>{{ $message }}</p>
            @enderror
            @php
                $mentionSuggestions = $priorVisitMentions['suggestions'] ?? [];
            @endphp
            @if ($mentionSuggestions !== [])
                <div class="ark-ro-mention__chips">
                    <p class="ark-ro-mention__hint">Previous visits — click or type @RO</p>
                    @foreach ($mentionSuggestions as $visit)
                        <button
                            type="button"
                            class="ark-ro-mention__chip{{ ! empty($visit['same_vehicle']) ? ' ark-ro-mention__chip--same' : '' }}"
                            @click="insertPriorVisit({{ \Illuminate\Support\Js::from($visit) }})"
                        >
                            <span class="ark-ro-mention__chip-label">{{ $visit['label'] }}</span>
                            @if (($visit['detail'] ?? '') !== '')
                                <span class="ark-ro-mention__chip-detail">{{ $visit['detail'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
            <div
                class="ops-worksheet-entry-intake__suggestions ark-ro-mention__menu"
                x-show="mentionOpen && mentionMatches.length > 0"
                x-cloak
                role="listbox"
                aria-label="Previous visits"
            >
                <template x-for="(row, index) in mentionMatches" :key="row.number">
                    <button
                        type="button"
                        class="ark-ro-mention__option"
                        :class="{ 'ark-ro-mention__option--active': index === mentionActiveIndex }"
                        role="option"
                        @mousedown.prevent="choosePriorVisit(row)"
                    >
                        <span class="font-semibold" x-text="row.label"></span>
                        <span class="block text-[11px] text-slate-500" x-text="row.detail"></span>
                    </button>
                </template>
            </div>
            <div
                class="ops-worksheet-entry-intake__suggestions"
                x-show="suggestionsOpen && hasMatches"
                @click.outside="suggestionsOpen = false; activeSuggestionIndex = -1"
                role="listbox"
                aria-label="Shop Memory suggestions"
            >
                <template x-if="featured?.summary">
                    <div class="ops-worksheet-entry-intake__featured">
                        <p class="ops-worksheet-entry-intake__featured-label">Most common at your shop</p>
                        <button
                            type="button"
                            class="ops-worksheet-entry-intake__suggestion ops-worksheet-entry-intake__suggestion--featured"
                            :class="{ 'ops-worksheet-entry-intake__suggestion--active': isSuggestionActive(featured.entry_kind, featured.summary) }"
                            @click="chooseSuggestion(featured.entry_kind, featured.summary, featured.concept_id, featured.suggestion_id, featured.provider)"
                        >
                            <span
                                class="ops-worksheet-entry-intake__suggestion-check"
                                x-show="isSuggestionActive(featured.entry_kind, featured.summary)"
                                aria-hidden="true"
                            >✓</span>
                            <span x-text="featured.summary"></span>
                        </button>
                    </div>
                </template>

                <template x-for="group in suggestionGroups" :key="group.entry_kind">
                    <div class="ops-worksheet-entry-intake__suggestion-group">
                        <p class="ops-worksheet-entry-intake__suggestion-label" x-text="group.label"></p>
                        <ul class="ops-worksheet-entry-intake__suggestion-list">
                            <template x-for="row in group.suggestions" :key="suggestionKey(group.entry_kind, typeof row === 'string' ? row : row.summary)">
                                <li role="option" :aria-selected="isSuggestionActive(group.entry_kind, typeof row === 'string' ? row : row.summary)">
                                    <button
                                        type="button"
                                        class="ops-worksheet-entry-intake__suggestion"
                                        :class="{ 'ops-worksheet-entry-intake__suggestion--active': isSuggestionActive(group.entry_kind, typeof row === 'string' ? row : row.summary) }"
                                        @click="chooseSuggestion(group.entry_kind, typeof row === 'string' ? row : row.summary, typeof row === 'object' ? row.concept_id : null, typeof row === 'object' ? row.suggestion_id : null, typeof row === 'object' ? row.provider : null)"
                                    >
                                        <span
                                            class="ops-worksheet-entry-intake__suggestion-check"
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

        @unless ($hideSubmitActions)
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="submit" class="ops-review-action ops-review-action--primary">
                    Create Concern
                </button>
                <template x-if="aiRewriteEnabled">
                    <button
                        type="button"
                        class="ops-review-action"
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
                        class="ops-review-action"
                        @click="rewriteSummary()"
                        :disabled="! summary.trim() || rewriting"
                    >
                        <span x-text="rewriting ? 'Rewriting…' : 'Rewrite'"></span>
                    </button>
                </div>
            </template>
        @endunless

        <p class="ops-worksheet-entry-intake__keyboard-hint">
            <span class="ops-worksheet-entry-intake__hint-label">Keyboard:</span>
            <span x-show="hasMatches">Enter fills top match · ↑↓ choose · Esc dismiss · Shift+Enter new line · Create Concern saves</span>
            <span x-show="! hasMatches">Enter or Create Concern continues · Shift+Enter new line</span>
        </p>
    </div>
</form>
