/**
 * Dragon Service Advisor — on-demand rewrite with before/after preview.
 * Never auto-applies. Polls assist status until completed/failed.
 */
export function arkDragonServiceAdvisor(config = {}) {
    return {
        requestUrl: config.requestUrl || '',
        requestUrlTemplate: config.requestUrlTemplate || '',
        statusUrlTemplate: config.statusUrlTemplate || '',
        applyUrlTemplate: config.applyUrlTemplate || '',
        revertUrlTemplate: config.revertUrlTemplate || '',
        estimateVersion: config.estimateVersion || null,
        csrfToken: config.csrfToken || '',
        fields: config.fields || [],
        modes: config.modes || [],
        defaultField: config.defaultField || 'verified_findings',
        field: config.defaultField || 'verified_findings',
        mode: config.defaultMode || 'service_advisor_rewrite',
        hideFieldPicker: Boolean(config.hideFieldPicker),
        lineId: config.lineId || null,
        phase: 'idle', // idle | generating | preview | applying | error
        assist: null,
        errorMessage: '',
        editedProposal: '',
        editing: false,
        pollTimer: null,
        pollCount: 0,
        maxPolls: 45,
        lastApplication: config.lastApplication || null,
        provenance: 'Dragon Service Advisor · Based on current RO/estimate context',

        resolveRequestUrl() {
            if (this.requestUrlTemplate && this.lineId) {
                return this.requestUrlTemplate.replace('__LINE__', encodeURIComponent(String(this.lineId)));
            }

            return this.requestUrl;
        },

        resolveApplyUrl(assistId) {
            let url = this.applyUrlTemplate.replace('__ASSIST__', encodeURIComponent(assistId));
            if (this.lineId) {
                url = url.replace('__LINE__', encodeURIComponent(String(this.lineId)));
            }

            return url;
        },

        get originalText() {
            return this.assist?.selected_text || '';
        },

        get proposalText() {
            return this.editing ? this.editedProposal : (this.assist?.proposal || '');
        },

        get canApply() {
            return this.phase === 'preview' && this.assist?.available && filled(this.proposalText);
        },

        get canGenerate() {
            if (this.phase === 'generating') {
                return false;
            }

            return ! (this.requestUrlTemplate && ! this.lineId);
        },

        workspaceModalData(source) {
            const el = source?.$el instanceof HTMLElement
                ? source.$el
                : (source instanceof HTMLElement ? source : this.$el);
            const host = el?.closest?.('#workspace-modal-host');

            if (! host || ! window.Alpine?.$data) {
                return null;
            }

            return window.Alpine.$data(host);
        },

        syncFieldFromContext(source) {
            const apply = () => {
                const ctx = this.workspaceModalData(source)?.context || {};

                if (ctx.field) {
                    this.field = ctx.field;
                }

                if (ctx.lineId != null) {
                    this.lineId = ctx.lineId;
                }
            };

            apply();

            if (typeof this.$watch === 'function' && ! this._watchingModalContext) {
                this._watchingModalContext = true;
                this.$watch(
                    () => {
                        const ctx = this.workspaceModalData(source)?.context || {};

                        return [ctx.field, ctx.lineId];
                    },
                    () => apply(),
                );
            }
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
            this.editedProposal = '';
            this.editing = false;
            this.pollCount = 0;
        },

        async generate() {
            const url = this.resolveRequestUrl();
            if (! url) {
                this.errorMessage = this.requestUrlTemplate && ! this.lineId
                    ? 'Open Generate from the note on this estimate.'
                    : 'Dragon is not ready on this panel.';

                return;
            }

            this.stopPoll();
            this.phase = 'generating';
            this.errorMessage = '';
            this.assist = null;
            this.editing = false;
            this.editedProposal = '';
            this.pollCount = 0;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        field: this.field,
                        mode: this.mode,
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    this.phase = 'error';
                    this.errorMessage = payload.message || 'Could not start Dragon Service Advisor.';

                    return;
                }

                this.assist = payload.assist || null;
                if (payload.provenance) {
                    this.provenance = payload.provenance;
                }

                if (this.assist?.available) {
                    this.enterPreview();

                    return;
                }

                if (this.assist?.status === 'failed') {
                    this.phase = 'error';
                    this.errorMessage = this.assist.error || "Dragon's rewrite was rejected.";

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
                    this.enterPreview();

                    return;
                }

                if (assist.status === 'failed') {
                    this.stopPoll();
                    this.phase = 'error';
                    this.errorMessage = assist.error || "Dragon's rewrite was rejected.";
                }
            } catch {
                // keep polling
            }
        },

        enterPreview() {
            this.phase = 'preview';
            this.editedProposal = this.assist?.proposal || '';
            this.editing = false;
        },

        startEdit() {
            this.editing = true;
            this.editedProposal = this.assist?.proposal || this.editedProposal;
        },

        cancelEdit() {
            this.editing = false;
            this.editedProposal = this.assist?.proposal || '';
        },

        async apply() {
            if (! this.canApply || ! this.applyUrlTemplate || ! this.assist?.request_id) {
                return;
            }

            this.phase = 'applying';
            const url = this.resolveApplyUrl(this.assist.request_id);
            const body = {
                opened_estimate_version: this.estimateVersion,
            };
            if (this.editing && this.editedProposal !== this.assist.proposal) {
                body.edited_proposal = this.editedProposal;
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
                    this.phase = 'preview';
                    this.errorMessage = payload.message || 'Could not apply rewrite.';

                    return;
                }

                this.lastApplication = payload.application || null;
                window.location.reload();
            } catch {
                this.phase = 'preview';
                this.errorMessage = 'Network error applying rewrite.';
            }
        },

        async revert() {
            if (! this.revertUrlTemplate || ! this.lastApplication?.public_id) {
                return;
            }

            const url = this.revertUrlTemplate.replace('__APP__', encodeURIComponent(this.lastApplication.public_id));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        opened_estimate_version: this.estimateVersion,
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    this.errorMessage = payload.message || 'Could not revert.';

                    return;
                }

                window.location.reload();
            } catch {
                this.errorMessage = 'Network error reverting rewrite.';
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

function filled(value) {
    return typeof value === 'string' ? value.trim() !== '' : Boolean(value);
}
