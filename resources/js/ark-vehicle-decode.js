const MAKE_ACRONYMS = new Set(['BMW', 'GMC', 'VW']);

const VEHICLE_LABEL_FIELDS = new Set(['make', 'model', 'trim']);

function isAllCaps(value) {
    return value === value.toUpperCase() && /[A-Z]/.test(value);
}

export function normalizeVehicleLabel(field, value) {
    if (typeof value !== 'string') {
        return value;
    }

    const trimmed = value.trim();

    if (trimmed === '') {
        return trimmed;
    }

    if (! isAllCaps(trimmed)) {
        return trimmed;
    }

    if (field === 'make' && MAKE_ACRONYMS.has(trimmed.toUpperCase())) {
        return trimmed.toUpperCase();
    }

    if (field === 'trim' && /^[A-Z0-9-]{2,5}$/.test(trimmed)) {
        return trimmed;
    }

    if ((field === 'model' || field === 'trim') && /\d/.test(trimmed)) {
        const lower = trimmed.toLowerCase();

        return lower.charAt(0).toUpperCase() + lower.slice(1);
    }

    return trimmed
        .toLowerCase()
        .replace(/(^|[\s-/])(\w)/g, (match, separator, character) => separator + character.toUpperCase());
}

export function normalizeVehicleFormLabels(form) {
    if (! form) {
        return;
    }

    for (const field of VEHICLE_LABEL_FIELDS) {
        const input = form.querySelector(`[name="${field}"]`);

        if (! input) {
            continue;
        }

        const normalized = normalizeVehicleLabel(field, input.value);

        if (normalized !== input.value) {
            input.value = normalized;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}

export const DECODED_VEHICLE_FORM_FIELDS = [
    'vin',
    'year',
    'make',
    'model',
    'trim',
    'engine',
    'drive',
    'transmission',
    'color',
    'plate',
    'plate_state',
    'nickname',
    'public_notes',
    'private_notes',
];

export function vehicleFormHasIdentity(form) {
    if (!form) {
        return false;
    }

    const value = (name) => (form.querySelector(`[name="${name}"]`)?.value ?? '').trim();
    const vin = value('vin');
    const plate = value('plate');
    const year = value('year');
    const make = value('make');
    const model = value('model');

    return vin !== '' || plate !== '' || (year !== '' && make !== '' && model !== '');
}

export function applyDecodedVehicleFields(form, payload) {
    if (!form || !payload) {
        return;
    }

    DECODED_VEHICLE_FORM_FIELDS.forEach((field) => {
        const value = payload[field];

        if (value === null || value === undefined) {
            return;
        }

        if (value === '' && field !== 'vin') {
            return;
        }

        const input = form.querySelector(`[name="${field}"]`);

        if (!input) {
            return;
        }

        const stringValue = field === 'vin'
            ? String(value).trim().toUpperCase()
            : String(value);

        const normalizedValue = VEHICLE_LABEL_FIELDS.has(field)
            ? normalizeVehicleLabel(field, stringValue)
            : stringValue;

        input.value = normalizedValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

export function arkVehicleDecode(config = {}) {
    const decodeUrl = config.decodeUrl ?? '';
    const csrfToken = config.csrfToken ?? '';

    return {
        decoding: false,
        message: '',
        async decodePlate(event) {
            const form = event.currentTarget.closest('form');
            const plate = form?.querySelector('[name="plate"]')?.value || '';
            const plateState = form?.querySelector('[name="plate_state"]')?.value || '';

            this.message = '';

            if (plate.trim().length < 2) {
                this.message = 'Enter a license plate to decode.';
                return;
            }

            if (plateState.trim().length < 2) {
                this.message = 'Enter the plate state (e.g. CO).';
                return;
            }

            this.decoding = true;

            try {
                const response = await fetch(decodeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        plate: plate.trim(),
                        plate_state: plateState.trim(),
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    this.message = payload.message || 'Vehicle could not be decoded from that plate.';
                    return;
                }

                applyDecodedVehicleFields(form, payload);

                this.message = 'Plate decoded. Review before saving.';
            } catch {
                this.message = 'Plate decode is unavailable right now.';
            } finally {
                this.decoding = false;
            }
        },
        async decode(event) {
            const form = event.currentTarget.closest('form');
            const vin = form?.querySelector('[name="vin"]')?.value || '';

            this.message = '';

            if (vin.trim().length < 11) {
                this.message = 'Enter at least 11 VIN characters to decode.';
                return;
            }

            this.decoding = true;

            try {
                const response = await fetch(decodeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ vin }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    this.message = payload.message || 'Vehicle could not be decoded.';
                    return;
                }

                applyDecodedVehicleFields(form, payload);

                this.message = 'Vehicle decoded. Review before saving.';
            } catch {
                this.message = 'Vehicle decode is unavailable right now.';
            } finally {
                this.decoding = false;
            }
        },
        normalizeField(event) {
            const input = event.target;
            const field = input?.name ?? '';

            if (! VEHICLE_LABEL_FIELDS.has(field)) {
                return;
            }

            const normalized = normalizeVehicleLabel(field, input.value);

            if (normalized !== input.value) {
                input.value = normalized;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
        guardSubmit(event) {
            const form = event.currentTarget;

            normalizeVehicleFormLabels(form);

            if (vehicleFormHasIdentity(form)) {
                return;
            }

            event.preventDefault();
            this.message = 'Enter a VIN, plate, or year, make, and model before saving.';
        },
    };
}
