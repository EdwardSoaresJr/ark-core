/**
 * Dragon Review Estimate Notes — whole-estimate or single-concern critique + proposals.
 * Nothing writes RO authority until the advisor Applies one proposal at a time.
 */
export function arkReviewEstimateNotes(config = {}) {
    return {
        requestUrl: config.requestUrl || '',
        statusUrlTemplate: config.statusUrlTemplate || '',
        applyUrlTemplate: config.applyUrlTemplate || '',
        estimateVersion: config.estimateVersion || null,
        csrfToken: config.csrfToken || '',
        concernId: config.concernId || null,
        phase: 'idle', // idle | generating | review | error
        assist: null,
        errorMessage: '',
        pollTimer: null,
        pollCount: 0,
        maxPolls: 60,
        proposalStates: {}, // key -> { skipped, applied, editing, editedText, applying, error }
        provenance: 'Dragon Review Estimate Notes · Nothing changes until you Apply a proposal',

        syncScopeFromContext(root) {
            const apply = () => {
                this.concernId = root?.context?.concernId ?? null;
            };
            apply();
            if (typeof this.$watch === 'function' && root) {
                this.$watch(
                    () => root.context?.concernId,
                    () => apply(),
                );
            }
        },

        proposalKey(p) {
            if (p.field === 'visit_reason') {
                return 'visit_reason';
            }
            if (p.field === 'line_note') {
                return `line:${p.line_id}`;
            }

            return `concern:${p.concern_id}:${p.field}`;
        },

        proposalState(p) {
            const key = this.proposalKey(p);
            if (! this.proposalStates[key]) {
                this.proposalStates[key] = {
                    skipped: false,
                    applied: false,
                    editing: false,
                    editedText: '',
                    applying: false,
                    error: '',
                };
            }

            return this.proposalStates[key];
        },

        fieldLabel(field) {
            const map = {
                summary: 'Concern summary',
                customer_states: 'Customer states',
                verified_findings: 'Verified findings',
                dtcs_summary: 'DTCs',
                recommendation: 'Recommendation',
                visit_reason: 'Reason for visit',
                line_note: 'Line note',
            };

            return map[field] || field;
        },

        stopPoll() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        reset() {
            this.stopPoll();
            this.phase = 'idle';
            this.assist = null;
            this.errorMessage = '';
            this.pollCount = 0;
            this.proposalStates = {};
        },

        async generate() {
            if (! this.requestUrl) {
                return;
            }

            this.stopPoll();
            this.phase = 'generating';
            this.errorMessage = '';
            this.assist = null;
            this.pollCount = 0;
            this.proposalStates = {};

            try {
                const body = {};
                if (this.concernId) {
                    body.concern_id = Number(this.concernId);
                }

                const response = await fetch(this.requestUrl, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    this.phase = 'error';
                    this.errorMessage = payload.message || 'Could not start Review Estimate Notes.';

                    return;
                }

                this.assist = payload.assist || null;
                if (payload.provenance) {
                    this.provenance = payload.provenance;
                }

                if (this.assist?.available) {
                    this.phase = 'review';

                    return;
                }

                this.startPoll();
            } catch {
                this.phase = 'error';
                this.errorMessage = 'Network error starting Dragon.';
            }
        },

        startPoll() {
            this.stopPoll();
            this.pollTimer = setInterval(() => this.pollOnce(), 2000);
            this.pollOnce();
        },

        async pollOnce() {
            if (! this.assist?.request_id || ! this.statusUrlTemplate) {
                return;
            }

            this.pollCount += 1;
            if (this.pollCount > this.maxPolls) {
                this.stopPoll();
                this.phase = 'error';
                this.errorMessage = 'Dragon is taking too long. Try again.';

                return;
            }

            const url = this.statusUrlTemplate.replace('__ASSIST__', encodeURIComponent(this.assist.request_id));

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));
                const assist = payload.assist || null;
                if (! assist) {
                    return;
                }

                this.assist = assist;

                if (assist.status === 'completed' && assist.available) {
                    this.stopPoll();
                    this.phase = 'review';

                    return;
                }

                if (assist.status === 'failed') {
                    this.stopPoll();
                    this.phase = 'error';
                    this.errorMessage = assist.error || 'Dragon critique failed.';
                }
            } catch {
                // keep polling
            }
        },

        skipProposal(p) {
            const state = this.proposalState(p);
            state.skipped = true;
            state.editing = false;
            state.error = '';
        },

        startEdit(p) {
            const state = this.proposalState(p);
            state.editing = true;
            state.editedText = p.proposed_text || '';
            state.error = '';
        },

        cancelEdit(p) {
            const state = this.proposalState(p);
            state.editing = false;
            state.editedText = '';
        },

        async applyProposal(p) {
            if (! p.applyable || ! this.applyUrlTemplate || ! this.assist?.request_id) {
                return;
            }

            const state = this.proposalState(p);
            if (state.applied || state.skipped || state.applying) {
                return;
            }

            state.applying = true;
            state.error = '';

            const url = this.applyUrlTemplate.replace('__ASSIST__', encodeURIComponent(this.assist.request_id));
            const body = {
                field: p.field,
                opened_estimate_version: this.estimateVersion,
            };
            if (p.concern_id) {
                body.concern_id = p.concern_id;
            }
            if (p.line_id) {
                body.line_id = p.line_id;
            }
            if (state.editing && state.editedText && state.editedText !== p.proposed_text) {
                body.edited_proposal = state.editedText;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    state.applying = false;
                    state.error = payload.message || 'Could not apply proposal.';

                    return;
                }

                state.applying = false;
                state.applied = true;
                state.editing = false;
                window.location.reload();
            } catch {
                state.applying = false;
                state.error = 'Network error applying proposal.';
            }
        },

        jsonHeaders() {
            return {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },
    };
}
