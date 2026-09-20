import { filterRoMentions, insertRoMentionToken, mentionFragmentAtCaret } from './ark-repair-order-mention';

/**
 * Add Concern compose — vocabulary (interim) or Shop Memory problem-language.
 * Suggestions populate the editor; Create Concern is authorship.
 */
export function arkScopeEntryIntake(configOrUrl, defaultEntryKind = '') {
    const config = typeof configOrUrl === 'string'
        ? {
            suggestUrl: configOrUrl,
            suggestMode: 'vocabulary',
            defaultEntryKind: defaultEntryKind,
            eventUrl: null,
            rewriteUrl: null,
            aiRewriteEnabled: false,
            repairOrderId: null,
            surface: 'add_concern',
        }
        : {
            suggestUrl: configOrUrl?.suggestUrl || '',
            suggestMode: configOrUrl?.suggestMode || 'vocabulary',
            defaultEntryKind: configOrUrl?.defaultEntryKind || defaultEntryKind || '',
            eventUrl: configOrUrl?.eventUrl || null,
            rewriteUrl: configOrUrl?.rewriteUrl || null,
            aiRewriteEnabled: Boolean(configOrUrl?.aiRewriteEnabled),
            repairOrderId: configOrUrl?.repairOrderId ?? null,
            surface: configOrUrl?.surface || 'add_concern',
            priorVisits: Array.isArray(configOrUrl?.priorVisits) ? configOrUrl.priorVisits : [],
        };

    return {
        entryKind: config.defaultEntryKind,
        selectedConceptId: '',
        summary: '',
        observedSummary: '',
        featured: null,
        suggestionGroups: [],
        hasMatches: false,
        suggestionsOpen: false,
        activeSuggestionIndex: -1,
        suggestAbort: null,
        suggestRequestId: 0,
        trace: null,
        aiRewriteEnabled: config.aiRewriteEnabled,
        rewriting: false,
        selectedSuggestionId: null,
        selectedSuggestionText: null,
        selectedProviderKey: null,
        suggestionsWereShown: false,
        authorityCreated: false,
        outcomePosted: false,
        priorVisits: config.priorVisits || [],
        mentionMatches: [],
        mentionOpen: false,
        mentionActiveIndex: -1,

        init() {
            this.trace = createScopeIntakeTrace();
        },

        get flatSuggestions() {
            const flat = [];

            if (this.featured?.summary) {
                flat.push({
                    entry_kind: this.featured.entry_kind,
                    groupLabel: 'Most common at your shop',
                    suggestion: this.featured.summary,
                    concept_id: this.featured.concept_id ?? null,
                    suggestion_id: this.featured.suggestion_id ?? null,
                    provider: this.featured.provider ?? null,
                });
            }

            for (const group of this.suggestionGroups) {
                for (const row of group.suggestions ?? []) {
                    const suggestion = typeof row === 'string' ? row : row.summary;

                    if (! suggestion) {
                        continue;
                    }

                    if (
                        this.featured
                        && suggestion === this.featured.summary
                        && group.entry_kind === this.featured.entry_kind
                    ) {
                        continue;
                    }

                    flat.push({
                        entry_kind: group.entry_kind,
                        groupLabel: group.label,
                        suggestion,
                        concept_id: typeof row === 'object' ? row.concept_id ?? null : null,
                        suggestion_id: typeof row === 'object' ? row.suggestion_id ?? null : null,
                        provider: typeof row === 'object' ? row.provider ?? null : null,
                    });
                }
            }

            return flat;
        },

        suggestionKey(entryKind, suggestion) {
            return `${entryKind}:${suggestion}`;
        },

        isSuggestionActive(entryKind, suggestion) {
            const flat = this.flatSuggestions;
            const index = flat.findIndex(
                (row) => row.entry_kind === entryKind && row.suggestion === suggestion,
            );

            return index >= 0 && index === this.activeSuggestionIndex;
        },

        recordTraceKey(key) {
            if (! this.trace?.keys) {
                return;
            }

            if (key === 'ArrowDown') {
                this.trace.keys.arrowDown += 1;
            } else if (key === 'ArrowUp') {
                this.trace.keys.arrowUp += 1;
            } else if (key === 'Escape') {
                this.trace.keys.escape += 1;
            } else if (key === 'Enter') {
                this.trace.keys.enter += 1;
            } else if (key === 'Backspace') {
                this.trace.keys.backspace += 1;
            }
        },

        recordFirstInput() {
            if (this.trace && ! this.trace.firstInputAt) {
                this.trace.firstInputAt = Date.now();
            }
        },

        finalizeTrace(submitVia) {
            if (! this.trace) {
                return;
            }

            this.trace.submittedAt = Date.now();
            this.trace.hadMatchesAtSubmit = this.hasMatches;
            this.trace.submitVia = submitVia;
            this.trace.queryAtSubmit = (this.observedSummary || this.summary).trim();
            this.trace.selectedSummary = this.summary.trim();
            this.trace.selectedEntryKind = this.entryKind || '';
            this.trace.matchCountAtSubmit = this.flatSuggestions.length;
            this.trace.observedVsSelected = {
                observed: (this.observedSummary || this.summary).trim(),
                selected: this.summary.trim(),
                diverged: (this.observedSummary || this.summary).trim() !== this.summary.trim(),
            };
            this.trace.translationValidation = this.buildTranslationValidation();

            persistScopeIntakeTrace(this.trace);
        },

        buildTranslationValidation() {
            const observed = (this.observedSummary || this.summary).trim();
            const selected = this.summary.trim();
            const diverged = observed !== '' && selected !== '' && observed !== selected;

            if (! this.hasMatches) {
                return {
                    recognition: 'novel',
                    translation: 'not_applicable',
                    resolution: 'pending',
                    note: 'Words survive verbatim — recognition without learned meaning is acceptable.',
                };
            }

            if (diverged) {
                return {
                    recognition: 'recognized',
                    translation: 'diverged',
                    resolution: 'pending',
                    note: 'Translation failure candidate — meaning may not have been preserved between dialects.',
                };
            }

            return {
                recognition: 'recognized',
                translation: 'aligned',
                resolution: 'pending',
                note: 'Observed language aligned with selected operational meaning.',
            };
        },

        handleSummaryInput() {
            this.recordFirstInput();
            this.observedSummary = this.summary;
            this.activeSuggestionIndex = -1;
            this.selectedConceptId = '';
            this.fetchSuggestions();
        },

        refreshPriorMentions() {
            const fragment = mentionFragmentAtCaret(this.$refs.summaryInput);
            this.mentionMatches = filterRoMentions(this.priorVisits, fragment);
            this.mentionOpen = this.mentionMatches.length > 0 && fragment !== '';

            if (this.mentionOpen) {
                this.suggestionsOpen = false;
                this.hasMatches = false;
                if (this.mentionActiveIndex < 0 || this.mentionActiveIndex >= this.mentionMatches.length) {
                    this.mentionActiveIndex = 0;
                }
            } else {
                this.mentionActiveIndex = -1;
            }
        },

        choosePriorVisit(row) {
            insertRoMentionToken(this.$refs.summaryInput, row.token);
            this.summary = this.$refs.summaryInput?.value || this.summary;
            this.mentionOpen = false;
            this.mentionActiveIndex = -1;
            this.refreshPriorMentions();
        },

        insertPriorVisit(row) {
            insertRoMentionToken(this.$refs.summaryInput, row.token);
            this.summary = this.$refs.summaryInput?.value || this.summary;
            this.mentionOpen = false;
            this.handleSummaryInput();
        },

        handleKeydown(event) {
            this.recordTraceKey(event.key);

            if (this.mentionOpen && this.mentionMatches.length > 0) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.mentionActiveIndex = this.mentionActiveIndex < this.mentionMatches.length - 1
                        ? this.mentionActiveIndex + 1
                        : 0;

                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.mentionActiveIndex = this.mentionActiveIndex > 0
                        ? this.mentionActiveIndex - 1
                        : this.mentionMatches.length - 1;

                    return;
                }

                if (event.key === 'Escape') {
                    this.mentionOpen = false;
                    this.mentionActiveIndex = -1;

                    return;
                }

                if (event.key === 'Enter' && ! event.shiftKey) {
                    event.preventDefault();
                    const index = this.mentionActiveIndex >= 0 ? this.mentionActiveIndex : 0;
                    this.choosePriorVisit(this.mentionMatches[index]);

                    return;
                }
            }

            const hasSuggestions = this.suggestionsOpen && this.hasMatches && this.flatSuggestions.length > 0;

            if (event.key === 'ArrowDown' && hasSuggestions) {
                event.preventDefault();
                this.activeSuggestionIndex = this.activeSuggestionIndex < this.flatSuggestions.length - 1
                    ? this.activeSuggestionIndex + 1
                    : 0;

                return;
            }

            if (event.key === 'ArrowUp' && hasSuggestions) {
                event.preventDefault();
                this.activeSuggestionIndex = this.activeSuggestionIndex > 0
                    ? this.activeSuggestionIndex - 1
                    : this.flatSuggestions.length - 1;

                return;
            }

            if (event.key === 'Escape') {
                this.suggestionsOpen = false;
                this.activeSuggestionIndex = -1;

                return;
            }

            // Shift+Enter inserts a newline in the textarea; Enter creates / accepts.
            if (event.key !== 'Enter' || event.shiftKey) {
                return;
            }

            event.preventDefault();

            if (hasSuggestions) {
                const index = this.activeSuggestionIndex >= 0 ? this.activeSuggestionIndex : 0;
                const selected = this.flatSuggestions[index];
                this.promoteIntoEditor(
                    selected.entry_kind,
                    selected.suggestion,
                    selected.concept_id,
                    selected.suggestion_id,
                    selected.provider,
                );

                return;
            }

            this.finalizeTrace('free_text');
            this.submitFreeText(event);
        },

        submitFreeText(event) {
            const form = event.target.closest('form');
            const text = this.summary.trim();

            if (! form || text === '') {
                return;
            }

            this.observedSummary = text;
            this.entryKind = '';
            this.selectedConceptId = '';
            this.suggestionsOpen = false;
            this.activeSuggestionIndex = -1;
            this.postTerminalOutcome(
                this.suggestionsWereShown ? 'ignored' : null,
            );
            this.authorityCreated = true;

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        },

        applySuggestionAndSubmit(entryKind, suggestion, conceptId, event, suggestionId = null, provider = null) {
            this.promoteIntoEditor(entryKind, suggestion, conceptId, suggestionId, provider);
            this.onFormSubmit();
            const form = event?.target?.closest?.('form') ?? this.$refs.summaryInput?.closest('form');

            if (form) {
                this.submitScopeForm(form, this.summary);
            }
        },

        submitScopeForm(form, summaryValue) {
            const input = this.$refs.summaryInput;

            if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
                input.value = summaryValue;
            }

            const observedField = form.querySelector('[name="observed_summary"]');

            if (observedField instanceof HTMLInputElement) {
                observedField.value = summaryValue;
            }

            const submit = () => {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(submit);

                return;
            }

            submit();
        },

        chooseSuggestion(entryKind, suggestion, conceptId = null, suggestionId = null, provider = null) {
            if (this.trace) {
                this.trace.suggestionClicks += 1;
            }

            this.promoteIntoEditor(entryKind, suggestion, conceptId, suggestionId, provider);
        },

        /**
         * Populate the editor only — Create Concern is authorship.
         */
        promoteIntoEditor(entryKind, suggestion, conceptId = null, suggestionId = null, provider = null) {
            this.entryKind = entryKind || config.defaultEntryKind;
            const resolved = this.resolveScopeSummary(this.observedSummary, suggestion);
            this.summary = resolved;
            this.observedSummary = this.observedSummary || resolved;
            this.selectedConceptId = conceptId ? String(conceptId) : '';
            this.selectedSuggestionId = suggestionId;
            this.selectedSuggestionText = suggestion;
            this.selectedProviderKey = provider;
            this.suggestionsOpen = false;
            this.activeSuggestionIndex = -1;
            this.$nextTick(() => this.$refs.summaryInput?.focus());
        },

        resolveScopeSummary(observed, selected) {
            const observedText = (observed || '').trim();
            const selectedText = (selected || '').trim();

            if (selectedText === '') {
                return observedText;
            }

            if (observedText === '' || observedText.toLowerCase() === selectedText.toLowerCase()) {
                return selectedText;
            }

            if (this.shouldPreferObservedOverAccidentalSubstring(observedText, selectedText)) {
                return observedText;
            }

            const positionPattern = /\b(front|rear|left|right|lf|rf|lr|rr|driver|passenger)\b/i;
            const observedLower = observedText.toLowerCase();
            const selectedLower = selectedText.toLowerCase();

            if (
                this.containsWholePhrase(observedLower, selectedLower)
                && observedText.length > selectedText.length
                && positionPattern.test(observedText)
                && ! positionPattern.test(selectedText)
            ) {
                return observedText;
            }

            return selectedText;
        },

        containsWholePhrase(haystack, needle) {
            const normalizedNeedle = (needle || '').trim().toLowerCase();

            if (normalizedNeedle === '') {
                return false;
            }

            const pattern = new RegExp(`\\b${normalizedNeedle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');

            return pattern.test(haystack);
        },

        shouldPreferObservedOverAccidentalSubstring(observed, selected) {
            const observedLower = observed.toLowerCase();
            const selectedLower = selected.toLowerCase();

            if (observed.length <= selected.length) {
                return false;
            }

            if (! observedLower.includes(selectedLower)) {
                return false;
            }

            return ! this.containsWholePhrase(observedLower, selectedLower);
        },

        async fetchSuggestions() {
            this.refreshPriorMentions();

            if (this.mentionOpen) {
                this.featured = null;
                this.suggestionGroups = [];
                this.hasMatches = false;
                this.suggestionsOpen = false;
                this.activeSuggestionIndex = -1;

                return;
            }

            const query = this.summary.trim();

            if (query.length < 2 || ! config.suggestUrl) {
                this.featured = null;
                this.suggestionGroups = [];
                this.hasMatches = false;
                this.suggestionsOpen = false;
                this.activeSuggestionIndex = -1;

                return;
            }

            if (this.suggestAbort) {
                this.suggestAbort.abort();
            }

            this.suggestAbort = new AbortController();
            const requestId = ++this.suggestRequestId;

            try {
                const url = new URL(config.suggestUrl, window.location.origin);
                url.searchParams.set('q', query);

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: this.suggestAbort.signal,
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json();

                if (requestId !== this.suggestRequestId) {
                    return;
                }

                if (config.suggestMode === 'shop_memory') {
                    this.applyShopMemoryPayload(payload);
                } else {
                    this.featured = payload.featured ?? null;
                    this.suggestionGroups = Array.isArray(payload.groups) ? payload.groups : [];
                    this.hasMatches = Boolean(payload.has_matches) && this.flatSuggestions.length > 0;
                }

                this.suggestionsOpen = this.hasMatches;
                this.activeSuggestionIndex = -1;

                if (this.hasMatches) {
                    this.suggestionsWereShown = true;
                }
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    this.featured = null;
                    this.suggestionGroups = [];
                    this.hasMatches = false;
                    this.suggestionsOpen = false;
                    this.activeSuggestionIndex = -1;
                }
            }
        },

        applyShopMemoryPayload(payload) {
            const rows = Array.isArray(payload.suggestions) ? payload.suggestions : [];
            this.featured = null;
            this.suggestionGroups = rows.length > 0
                ? [{
                    entry_kind: config.defaultEntryKind || 'customer_requested',
                    label: 'Shop Memory',
                    suggestions: rows.map((row) => ({
                        summary: row.text,
                        suggestion_id: row.id,
                        provider: row.provider,
                        concept_id: null,
                    })),
                }]
                : [];
            this.hasMatches = this.flatSuggestions.length > 0;
        },

        async rewriteSummary() {
            if (! this.aiRewriteEnabled || ! config.rewriteUrl || this.rewriting) {
                return;
            }

            const text = this.summary.trim();

            if (text === '') {
                return;
            }

            this.rewriting = true;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(config.rewriteUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ text }),
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json();

                if (payload.text) {
                    this.summary = payload.text;
                    this.observedSummary = payload.text;
                }
            } finally {
                this.rewriting = false;
            }
        },

        maybeRecordDismiss() {
            if (this.authorityCreated || this.outcomePosted) {
                return;
            }

            if (! this.suggestionsWereShown && ! this.summary.trim()) {
                return;
            }

            this.postTerminalOutcome('dismissed');
        },

        onFormSubmit() {
            if (! this.outcomePosted) {
                if (this.selectedSuggestionText) {
                    const outcome = this.summary.trim() === this.selectedSuggestionText.trim()
                        ? 'accepted_unchanged'
                        : 'accepted_edited';
                    this.postTerminalOutcome(
                        outcome,
                        this.selectedSuggestionId,
                        this.selectedProviderKey,
                    );
                } else if (this.suggestionsWereShown) {
                    this.postTerminalOutcome('ignored');
                }
            }

            this.authorityCreated = true;
        },

        postTerminalOutcome(outcome, suggestionId = null, provider = null) {
            if (! outcome || this.outcomePosted || ! config.eventUrl) {
                return;
            }

            this.outcomePosted = true;

            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const body = {
                provider_key: provider || this.selectedProviderKey || 'scope_entry',
                suggestion_id: suggestionId || this.selectedSuggestionId,
                outcome,
                surface: config.surface,
                query: (this.observedSummary || this.summary || '').trim().slice(0, 255) || null,
                repair_order_id: config.repairOrderId,
            };

            fetch(config.eventUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
                keepalive: true,
            }).catch(() => {
                // Observation capture must never block authorship.
            });
        },
    };
}

function createScopeIntakeTrace() {
    return {
        firstInputAt: null,
        submittedAt: null,
        repairActionFocusedAt: null,
        keys: {
            arrowDown: 0,
            arrowUp: 0,
            escape: 0,
            enter: 0,
            backspace: 0,
        },
        suggestionClicks: 0,
        hadMatchesAtSubmit: false,
        matchCountAtSubmit: 0,
        submitVia: null,
        queryAtSubmit: '',
        selectedSummary: '',
    };
}

function persistScopeIntakeTrace(trace) {
    const payload = {
        ...trace,
        msToSubmit: trace.firstInputAt && trace.submittedAt
            ? trace.submittedAt - trace.firstInputAt
            : null,
        msToRepairActionFocus: trace.firstInputAt && trace.repairActionFocusedAt
            ? trace.repairActionFocusedAt - trace.firstInputAt
            : null,
    };

    try {
        sessionStorage.setItem('ark.scopeIntake.lastTrace', JSON.stringify(payload));
    } catch {
        // sessionStorage unavailable — floor pass can still read window global
    }

    window.__arkScopeIntakeLastTrace = payload;
}

/** Floor pass: in devtools console, run `copy(JSON.stringify(window.__arkScopeIntakeLastTrace, null, 2))` */
export function readScopeIntakeTrace() {
    return window.__arkScopeIntakeLastTrace ?? null;
}

export function focusWorksheetConcernRepairAction() {
    const marker = document.querySelector('[data-worksheet-focus-concern]');

    if (! marker) {
        return;
    }

    const concernId = marker.dataset.worksheetFocusConcern;
    const input = document.querySelector(`#concern-${concernId} [data-concern-repair-action-input]`);

    if (input instanceof HTMLElement) {
        input.focus();
        input.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    }

    try {
        const raw = sessionStorage.getItem('ark.scopeIntake.lastTrace');

        if (raw) {
            const trace = JSON.parse(raw);
            trace.repairActionFocusedAt = Date.now();
            trace.msToRepairActionFocus = trace.firstInputAt
                ? trace.repairActionFocusedAt - trace.firstInputAt
                : null;
            sessionStorage.setItem('ark.scopeIntake.lastTrace', JSON.stringify(trace));
            window.__arkScopeIntakeLastTrace = trace;
        }
    } catch {
        // ignore
    }

    marker.remove();
}
