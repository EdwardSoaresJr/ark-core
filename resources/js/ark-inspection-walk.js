export function arkInspectionWalk(config = {}) {
    const slots = (config.measurementSlots ?? []).map((slot) => ({
        key: slot.key,
        name: slot.name,
        unit: slot.unit ?? '',
        required: !!slot.required,
        type: slot.type ?? 'number',
        value: slot.value ?? '',
    }));

    return {
        updateUrl: config.updateUrl ?? '',
        csrf: config.csrf ?? '',
        currentStatus: config.currentStatus ?? null,
        note: config.note ?? '',
        savedNote: config.note ?? '',
        measurementValue: config.measurementValue ?? '',
        savedMeasurementValue: config.measurementValue ?? '',
        measurementUnit: config.measurementUnit ?? '',
        savedMeasurementUnit: config.measurementUnit ?? '',
        measurementSlots: slots,
        savedSlotsKey: JSON.stringify(slots.map((slot) => [slot.key, slot.value])),
        rearAxleBrakeType: config.rearAxleBrakeType ?? null,
        isAxleGate: !!config.isAxleGate,
        roadTestFindingLocked: !!config.roadTestFindingLocked,
        roadTestForceNa: !!config.roadTestForceNa,
        brakePrompts: config.brakePrompts ?? [],
        missingSlots: config.missingSlots ?? [],
        saving: false,
        saved: false,
        saveError: false,
        savedTimer: null,

        init() {
            if (this.roadTestForceNa && this.currentStatus !== 'na') {
                this.currentStatus = 'na';
            }
        },

        async setCondition(status) {
            if (! this.updateUrl) {
                return;
            }

            if (status === this.currentStatus && ! this.saveError) {
                return;
            }

            if (this.roadTestFindingLocked) {
                return;
            }

            if (this.roadTestForceNa && status !== 'na') {
                return;
            }

            this.currentStatus = status;
            const payload = { status };
            if (this.isAxleGate && this.rearAxleBrakeType) {
                payload.rear_axle_brake_type = this.rearAxleBrakeType;
            }
            await this.patch(payload);
        },

        async setRearAxle(type) {
            this.rearAxleBrakeType = type;
            this.currentStatus = 'good';
            await this.patch({
                status: 'good',
                rear_axle_brake_type: type,
            });
            // Axle choice changes visible walk points - reload host.
            window.location.reload();
        },

        async saveNote() {
            if (! this.updateUrl) {
                return;
            }

            if (this.note === this.savedNote && ! this.saveError) {
                return;
            }

            const note = this.note;
            await this.patch({ note, status: this.currentStatus });

            if (! this.saveError) {
                this.savedNote = note;
            }
        },

        async saveMeasurement() {
            if (! this.updateUrl) {
                return;
            }

            const unchanged = this.measurementValue === this.savedMeasurementValue
                && this.measurementUnit === this.savedMeasurementUnit;

            if (this.measurementValue === '' || (unchanged && ! this.saveError)) {
                return;
            }

            const measurementValue = this.measurementValue;
            const measurementUnit = this.measurementUnit;
            await this.patch({
                status: this.currentStatus,
                measurement_value: measurementValue,
                measurement_unit: measurementUnit || null,
            });

            if (! this.saveError) {
                this.savedMeasurementValue = measurementValue;
                this.savedMeasurementUnit = measurementUnit;
            }
        },

        async saveSlots() {
            if (! this.updateUrl || this.measurementSlots.length === 0) {
                return;
            }

            const measurements = this.measurementSlots
                .filter((slot) => String(slot.value ?? '').trim() !== '')
                .map((slot) => ({
                    key: slot.key,
                    name: slot.name,
                    value: String(slot.value).trim(),
                    unit: slot.unit || null,
                }));

            if (measurements.length === 0) {
                return;
            }

            const nextKey = JSON.stringify(measurements);

            if (nextKey === this.savedSlotsKey && ! this.saveError) {
                return;
            }

            await this.patch({
                status: this.currentStatus ?? 'good',
                measurements,
            });

            if (! this.saveError) {
                this.savedSlotsKey = nextKey;
            }
        },

        async patch(payload) {
            this.saving = true;
            this.saved = false;
            this.saveError = false;

            try {
                const body = new FormData();
                Object.entries(payload).forEach(([key, value]) => {
                    if (value === null || value === undefined) {
                        return;
                    }

                    if (key === 'measurements' && Array.isArray(value)) {
                        value.forEach((row, index) => {
                            Object.entries(row).forEach(([field, fieldValue]) => {
                                if (fieldValue !== null && fieldValue !== undefined) {
                                    body.append(`measurements[${index}][${field}]`, fieldValue);
                                }
                            });
                        });
                        return;
                    }

                    body.append(key, value);
                });
                body.append('_method', 'PATCH');

                const response = await fetch(this.updateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body,
                });

                if (! response.ok) {
                    this.saveError = true;
                    return;
                }

                this.saveError = false;
                const data = await response.json();
                if (data.living_record?.status) {
                    this.currentStatus = data.living_record.status;
                }
                if (Array.isArray(data.brake_prompts)) {
                    this.brakePrompts = data.brake_prompts;
                }
                if (Array.isArray(data.follow_up?.missing_measurement_slots)) {
                    this.missingSlots = data.follow_up.missing_measurement_slots;
                }

                this.flashSaved();
            } catch (error) {
                this.saveError = true;
            } finally {
                this.saving = false;
            }
        },

        flashSaved() {
            this.saved = true;
            clearTimeout(this.savedTimer);
            this.savedTimer = setTimeout(() => {
                this.saved = false;
            }, 1200);
        },
    };
}
