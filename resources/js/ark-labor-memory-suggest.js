/**
 * Shop Memory — labor description reuse.
 * Type → suggest → ↓ Enter. No AI. No loading chrome.
 */
export function arkLaborMemorySuggest(suggestUrlOrConfig, initialDescription = '') {
    const config = typeof suggestUrlOrConfig === 'string'
        ? {
            suggestUrl: suggestUrlOrConfig,
            eventUrl: null,
            repairOrderId: null,
            surface: 'labor_entry',
        }
        : {
            suggestUrl: suggestUrlOrConfig?.suggestUrl || '',
            eventUrl: suggestUrlOrConfig?.eventUrl || null,
            repairOrderId: suggestUrlOrConfig?.repairOrderId ?? null,
            surface: suggestUrlOrConfig?.surface || 'labor_entry',
        };

    return {
        suggestUrl: config.suggestUrl,
        description: initialDescription || '',
        suggestions: [],
        suggestionsOpen: false,
        activeIndex: -1,
        suggestAbort: null,
        requestId: 0,
        selectedRow: null,
        suggestionsWereShown: false,
        outcomePosted: false,
        ignoreFocusFetch: false,
        blurCloseTimer: null,

        get hasMatches() {
            return this.suggestions.length > 0;
        },

        handleInput() {
            this.ignoreFocusFetch = false;
            this.fetchSuggestions();
        },

        handleKeydown(event) {
            if (! this.suggestionsOpen || ! this.hasMatches) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, this.suggestions.length - 1);

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.closeSuggestions({ dismiss: true });

                return;
            }

            if (event.key === 'Enter' && this.activeIndex >= 0) {
                event.preventDefault();
                this.chooseSuggestion(this.suggestions[this.activeIndex]);
            }
        },

        handleFocus() {
            if (this.ignoreFocusFetch) {
                this.ignoreFocusFetch = false;

                return;
            }

            this.fetchSuggestions();
        },

        handleBlur() {
            if (this.blurCloseTimer) {
                window.clearTimeout(this.blurCloseTimer);
            }

            // Let suggestion mousedown run before closing (mousedown.prevent skips blur on click).
            this.blurCloseTimer = window.setTimeout(() => {
                this.blurCloseTimer = null;
                this.closeSuggestions();
                this.commitOutcomeOnBlur();
            }, 120);
        },

        chooseSuggestion(row) {
            if (! row?.text) {
                return;
            }

            if (this.blurCloseTimer) {
                window.clearTimeout(this.blurCloseTimer);
                this.blurCloseTimer = null;
            }

            this.selectedRow = row;
            this.description = row.text;
            // Choosing re-focuses the input so the advisor can tweak wording;
            // that focus must not reopen the same suggestion list over hours/rate.
            this.ignoreFocusFetch = true;
            this.closeSuggestions();
            this.$nextTick(() => {
                this.$refs.descriptionInput?.focus();
            });
        },

        abortPendingFetch() {
            if (this.suggestAbort) {
                this.suggestAbort.abort();
                this.suggestAbort = null;
            }

            this.requestId += 1;
        },

        closeSuggestions({ dismiss = false } = {}) {
            this.abortPendingFetch();
            this.suggestionsOpen = false;
            this.activeIndex = -1;

            if (dismiss && this.suggestionsWereShown && ! this.selectedRow) {
                this.postTerminalOutcome('dismissed');
            }
        },

        commitOutcomeOnBlur() {
            if (this.outcomePosted) {
                return;
            }

            if (this.selectedRow) {
                const outcome = this.description.trim() === this.selectedRow.text.trim()
                    ? 'accepted_unchanged'
                    : 'accepted_edited';
                this.postTerminalOutcome(outcome, this.selectedRow.id, this.selectedRow.provider);

                return;
            }

            if (this.suggestionsWereShown && this.description.trim() !== '') {
                this.postTerminalOutcome('ignored');
            }
        },

        postTerminalOutcome(outcome, suggestionId = null, provider = null) {
            if (! outcome || this.outcomePosted || ! config.eventUrl) {
                return;
            }

            this.outcomePosted = true;

            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(config.eventUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                credentials: 'same-origin',
                keepalive: true,
                body: JSON.stringify({
                    provider_key: provider || 'historical_labor',
                    suggestion_id: suggestionId,
                    outcome,
                    surface: config.surface,
                    query: (this.description || '').trim().slice(0, 255) || null,
                    repair_order_id: config.repairOrderId,
                }),
            }).catch(() => {});
        },

        async fetchSuggestions() {
            const query = (this.description || '').trim();

            if (query.length < 2 || ! this.suggestUrl) {
                this.suggestions = [];
                this.suggestionsOpen = false;
                this.activeIndex = -1;

                return;
            }

            if (this.suggestAbort) {
                this.suggestAbort.abort();
            }

            this.suggestAbort = new AbortController();
            const requestId = ++this.requestId;

            try {
                const url = new URL(this.suggestUrl, window.location.origin);
                url.searchParams.set('q', query);

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: this.suggestAbort.signal,
                });

                if (! response.ok || requestId !== this.requestId) {
                    return;
                }

                const payload = await response.json();

                if (requestId !== this.requestId) {
                    return;
                }

                this.suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
                this.suggestionsOpen = this.suggestions.length > 0;
                this.activeIndex = this.suggestions.length > 0 ? 0 : -1;

                if (this.suggestions.length > 0) {
                    this.suggestionsWereShown = true;
                }
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                if (requestId !== this.requestId) {
                    return;
                }

                this.suggestions = [];
                this.suggestionsOpen = false;
                this.activeIndex = -1;
            }
        },
    };
}
