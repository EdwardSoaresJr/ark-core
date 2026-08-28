/**
 * Workspace Modal Saved Work picker — search, Historical Work Recall, Dragon Assist (async).
 */
export function arkSavedWorkPicker(config = {}) {
    return {
        searchUrl: config.searchUrl || '',
        recallUrlTemplate: config.recallUrlTemplate || '',
        assistUrlTemplate: config.assistUrlTemplate || '',
        assistStatusUrlTemplate: config.assistStatusUrlTemplate || '',
        concernId: config.concernId || null,
        intents: config.intents || [],
        recommendationIntent: config.defaultIntent || 'maintenance',
        query: '',
        results: [],
        selected: null,
        selectedId: null,
        recall: null,
        assist: null,
        assistStatus: null,
        assistPollTimer: null,
        laborHours: '',
        laborConfirmed: false,
        loading: false,
        recallLoading: false,

        async boot() {
            this.syncFromModal();
            await this.search();
        },

        syncFromModal() {
            const apply = () => {
                const modal = this.$el?.closest?.('#workspace-modal-host');
                const data = modal ? window.Alpine?.$data?.(modal) : null;
                const fromContext = data?.context?.concernId;
                this.concernId = fromContext != null && fromContext !== '' ? fromContext : null;
            };

            apply();

            const modal = this.$el?.closest?.('#workspace-modal-host');
            const data = modal ? window.Alpine?.$data?.(modal) : null;

            if (typeof this.$watch === 'function' && data) {
                this.$watch(
                    () => data.context?.concernId,
                    () => apply(),
                );
            }
        },

        async search() {
            if (! this.searchUrl) {
                return;
            }

            this.loading = true;

            try {
                const url = new URL(this.searchUrl, window.location.origin);
                if (this.query.trim() !== '') {
                    url.searchParams.set('q', this.query.trim());
                }

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    this.results = [];

                    return;
                }

                const payload = await response.json();
                this.results = Array.isArray(payload.templates) ? payload.templates : [];
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        async select(item) {
            this.stopAssistPoll();
            this.selected = item;
            this.selectedId = item?.id ?? null;
            this.recall = null;
            this.assist = null;
            this.assistStatus = null;
            this.laborConfirmed = false;
            this.laborHours = this.templateLaborHours(item) ?? '';
            this.recommendationIntent = item?.recommendation_intent || this.recommendationIntent;

            if (! this.selectedId || ! this.recallUrlTemplate) {
                return;
            }

            this.recallLoading = true;

            try {
                const url = this.recallUrlTemplate.replace('__TEMPLATE__', String(this.selectedId));
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json();
                this.recall = payload.recall || null;

                if (this.recall?.prepares_labor && this.recall.preview_labor_hours != null) {
                    this.laborHours = String(this.recall.preview_labor_hours);
                } else if (this.recall?.template_default_hours != null) {
                    this.laborHours = String(this.recall.template_default_hours);
                }

                if (this.recall && this.recall.tier !== 'none') {
                    this.requestAssist();
                }
            } catch {
                this.recall = null;
            } finally {
                this.recallLoading = false;
            }
        },

        async requestAssist() {
            if (! this.assistUrlTemplate || ! this.selectedId) {
                return;
            }

            this.assistStatus = 'reviewing';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = this.assistUrlTemplate.replace('__TEMPLATE__', String(this.selectedId));
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });

                if (! response.ok) {
                    this.assistStatus = null;

                    return;
                }

                const payload = await response.json();
                this.assist = payload.assist || null;

                if (this.assist?.available) {
                    this.assistStatus = 'ready';

                    return;
                }

                if (this.assist?.request_id) {
                    this.pollAssist(this.assist.request_id);
                } else {
                    this.assistStatus = null;
                }
            } catch {
                this.assistStatus = null;
            }
        },

        pollAssist(requestId) {
            this.stopAssistPoll();
            let attempts = 0;

            this.assistPollTimer = window.setInterval(async () => {
                attempts += 1;
                if (attempts > 30 || ! this.assistStatusUrlTemplate) {
                    this.stopAssistPoll();
                    this.assistStatus = null;

                    return;
                }

                try {
                    const url = this.assistStatusUrlTemplate.replace('__ASSIST__', encodeURIComponent(requestId));
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (! response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    this.assist = payload.assist || null;

                    if (this.assist?.available || this.assist?.status === 'failed') {
                        this.assistStatus = this.assist?.available ? 'ready' : null;
                        this.stopAssistPoll();
                    }
                } catch {
                    // Keep polling quietly.
                }
            }, 2000);
        },

        stopAssistPoll() {
            if (this.assistPollTimer) {
                window.clearInterval(this.assistPollTimer);
                this.assistPollTimer = null;
            }
        },

        templateLaborHours(item) {
            const labor = (item?.lines || []).find((line) => line.type === 'labor');

            return labor?.hours ?? labor?.quantity ?? null;
        },

        canSubmit() {
            if (! this.selectedId) {
                return false;
            }

            if (this.recall?.requires_review && ! this.laborConfirmed) {
                return false;
            }

            return true;
        },

        applyHoursValue() {
            if (! this.recall?.prepares_labor) {
                return '';
            }

            if (this.recall.requires_review && ! this.laborConfirmed) {
                return '';
            }

            return this.laborHours || '';
        },
    };
}
