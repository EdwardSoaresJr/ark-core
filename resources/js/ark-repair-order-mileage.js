const normalizeMileage = (value) => {
    const cleaned = String(value ?? '').replace(/,/g, '').trim();

    if (cleaned === '') {
        return null;
    }

    const parsed = Number.parseInt(cleaned, 10);

    return Number.isFinite(parsed) ? parsed : null;
};

const formatMileageInput = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value);
};

const formatMileageDisplay = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return Number(value).toLocaleString('en-US');
};

export const arkRepairOrderMileage = (config) => ({
    mileageIn: formatMileageInput(config.mileageIn),
    mileageOut: formatMileageInput(config.mileageOut),
    legacyMileageIn: normalizeMileage(config.legacyMileageIn),
    estimateVersion: config.estimateVersion,
    estimateVersionField: config.estimateVersionField,
    url: config.url,
    csrf: config.csrf,
    saving: false,
    error: null,
    editingIn: false,
    editingOut: false,
    savedMileageIn: normalizeMileage(config.mileageIn),
    savedMileageOut: normalizeMileage(config.mileageOut),
    normalizeMileage,
    displayIn() {
        return formatMileageDisplay(this.savedMileageIn ?? this.legacyMileageIn);
    },
    displayOut() {
        return formatMileageDisplay(this.savedMileageOut);
    },
    openIn() {
        this.editingIn = true;
        this.error = null;

        this.$nextTick(() => this.$refs.mileageInInput?.focus());
    },
    openOut() {
        this.editingOut = true;
        this.error = null;

        this.$nextTick(() => this.$refs.mileageOutInput?.focus());
    },
    cancelIn() {
        this.mileageIn = formatMileageInput(this.savedMileageIn);
        this.editingIn = false;
        this.error = null;
    },
    cancelOut() {
        this.mileageOut = formatMileageInput(this.savedMileageOut);
        this.editingOut = false;
        this.error = null;
    },
    async finishIn() {
        await this.save();

        if (! this.error) {
            this.editingIn = false;
        }
    },
    async finishOut() {
        await this.save();

        if (! this.error) {
            this.editingOut = false;
        }
    },
    async save() {
        const mileageIn = this.normalizeMileage(this.mileageIn);
        const mileageOut = this.normalizeMileage(this.mileageOut);

        if (mileageIn === this.savedMileageIn && mileageOut === this.savedMileageOut) {
            return;
        }

        this.saving = true;
        this.error = null;

        const body = new FormData();
        body.append('_token', this.csrf);
        body.append('_method', 'PATCH');
        body.append(this.estimateVersionField, this.estimateVersion);

        if (mileageIn !== null) {
            body.append('mileage_in', String(mileageIn));
        }

        if (mileageOut !== null) {
            body.append('mileage_out', String(mileageOut));
        }

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
                this.error = payload?.errors?.mileage_out?.[0]
                    ?? payload?.errors?.mileage_in?.[0]
                    ?? 'Invalid mileage.';

                return;
            }

            if (! response.ok) {
                throw new Error('mileage save failed');
            }

            const payload = await response.json();

            this.mileageIn = formatMileageInput(payload.mileage_in);
            this.mileageOut = formatMileageInput(payload.mileage_out);
            this.savedMileageIn = normalizeMileage(payload.mileage_in);
            this.savedMileageOut = normalizeMileage(payload.mileage_out);

            if (payload.estimate_version) {
                this.estimateVersion = payload.estimate_version;

                document
                    .querySelectorAll(`input[name="${CSS.escape(this.estimateVersionField)}"]`)
                    .forEach((input) => {
                        input.value = payload.estimate_version;
                    });
            }
        } catch {
            this.error = 'Could not save mileage.';
        } finally {
            this.saving = false;
        }
    },
});
