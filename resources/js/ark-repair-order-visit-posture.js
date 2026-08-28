export const arkRepairOrderVisitPosture = (config) => ({
    editing: false,
    canEdit: config.canEdit ?? false,
    visitMode: config.visitMode ?? '',
    savedVisitMode: config.visitMode ?? '',
    visitModeLabels: config.visitModeLabels ?? {},
    repairOrderLabel: config.repairOrderLabel ?? '',
    url: config.url,
    csrf: config.csrf,
    saving: false,
    error: null,

    visitModeLabel() {
        return this.visitModeLabels[this.visitMode] ?? 'Not set';
    },

    open() {
        if (! this.canEdit) {
            return;
        }

        this.editing = true;
        this.error = null;

        this.$nextTick(() => {
            this.$refs.visitSelect?.focus();
        });
    },

    cancel() {
        this.visitMode = this.savedVisitMode;
        this.editing = false;
        this.error = null;
    },

    async save(nextMode) {
        if (nextMode === '' || nextMode === this.savedVisitMode) {
            this.visitMode = this.savedVisitMode;
            this.editing = false;

            return;
        }

        this.saving = true;
        this.error = null;

        const body = new FormData();
        body.append('_token', this.csrf);
        body.append('_method', 'PATCH');
        body.append('visit_mode', nextMode);

        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body,
            });

            if (response.status === 422) {
                const payload = await response.json();
                this.error = payload?.errors?.visit_mode?.[0]
                    ?? payload?.message
                    ?? 'Could not update visit type.';
                this.visitMode = this.savedVisitMode;

                return;
            }

            if (! response.ok) {
                throw new Error('visit posture save failed');
            }

            const payload = await response.json();
            this.visitMode = payload.visit_mode ?? nextMode;
            this.savedVisitMode = this.visitMode;
            this.editing = false;
        } catch {
            this.error = 'Could not update visit type.';
            this.visitMode = this.savedVisitMode;
        } finally {
            this.saving = false;
        }
    },
});
