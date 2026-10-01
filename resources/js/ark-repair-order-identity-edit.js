import { syncEstimateVersionInputs } from './ark-worksheet-collaboration';

const snapshot = (values) => JSON.stringify(values);

const patchIdentity = async (config, fields) => {
    const body = new FormData();
    body.append('_token', config.csrf);
    body.append('_method', 'PATCH');
    body.append('repair_order_id', String(config.repairOrderId));

    Object.entries(fields).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
            body.append(key, String(value));
        }
    });

    const response = await fetch(config.url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body,
    });

    const contentType = response.headers.get('content-type') ?? '';
    const isJson = contentType.includes('application/json');

    if (response.status === 422 && isJson) {
        const payload = await response.json();
        const firstError = Object.values(payload?.errors ?? {})[0]?.[0];

        throw new Error(firstError ?? 'Invalid value.');
    }

    if (! response.ok || ! isJson) {
        throw new Error('Could not save. Refresh and try again.');
    }

    return response.json();
};

const syncEstimateVersion = (payload, estimateVersionField) => {
    if (! payload?.estimate_version) {
        return;
    }

    syncEstimateVersionInputs(estimateVersionField, payload.estimate_version);
};

export const arkRepairOrderIdentityCustomer = (config) => ({
    editing: false,
    saving: false,
    error: null,
    title: config.title,
    lines: config.lines,
    firstName: config.firstName,
    lastName: config.lastName,
    phone: config.phone ?? '',
    email: config.email ?? '',
    contactPreference: config.contactPreference ?? '',
    addressLine1: config.addressLine1 ?? '',
    addressLine2: config.addressLine2 ?? '',
    city: config.city ?? '',
    state: config.state ?? '',
    postalCode: config.postalCode ?? '',
    customerType: config.customerType,
    notes: config.notes ?? '',
    savedSnapshot: snapshot({
        firstName: config.firstName,
        lastName: config.lastName,
        phone: config.phone ?? '',
        email: config.email ?? '',
        contactPreference: config.contactPreference ?? '',
        addressLine1: config.addressLine1 ?? '',
        addressLine2: config.addressLine2 ?? '',
        city: config.city ?? '',
        state: config.state ?? '',
        postalCode: config.postalCode ?? '',
        customerType: config.customerType ?? 'Retail',
    }),
    url: config.url,
    csrf: config.csrf,
    repairOrderId: config.repairOrderId,
    estimateVersionField: config.estimateVersionField,
    serviceLaneLayout: config.serviceLaneLayout ?? false,
    recognitionMeta() {
        if (! this.serviceLaneLayout) {
            return [];
        }

        const parts = [];
        const reachLine = (this.lines ?? []).find((line) => line.label === 'Reach via');

        if (reachLine?.value) {
            parts.push({
                key: 'reach',
                value: reachLine.value,
                muted: true,
            });
        }

        const phoneLine = (this.lines ?? []).find((line) => line.label === 'Phone');

        if (phoneLine?.value) {
            parts.push({
                key: 'phone',
                value: phoneLine.value,
                href: phoneLine.href ?? null,
            });
        }

        parts.push({
            key: 'billing',
            value: this.customerType || 'Retail',
        });

        const referralLine = (this.lines ?? []).find((line) => line.label === 'Referral');

        if (referralLine?.value) {
            parts.push({
                key: 'referral',
                value: referralLine.value,
                muted: true,
            });
        }

        return parts;
    },
    open() {
        this.editing = true;
        this.error = null;
    },
    cancel() {
        const parsed = JSON.parse(this.savedSnapshot);
        this.firstName = parsed.firstName;
        this.lastName = parsed.lastName;
        this.phone = parsed.phone;
        this.email = parsed.email;
        this.contactPreference = parsed.contactPreference ?? '';
        this.addressLine1 = parsed.addressLine1;
        this.addressLine2 = parsed.addressLine2;
        this.city = parsed.city;
        this.state = parsed.state;
        this.postalCode = parsed.postalCode;
        this.customerType = parsed.customerType ?? 'Retail';
        this.editing = false;
        this.error = null;
    },
    applyPayload(payload) {
        if (! payload?.customer) {
            return;
        }

        this.title = payload.customer.title;
        this.lines = payload.customer.lines ?? [];
        if (payload.customer.type) {
            this.customerType = payload.customer.type;
        }
        this.savedSnapshot = snapshot({
            firstName: this.firstName,
            lastName: this.lastName,
            phone: this.phone,
            email: this.email,
            contactPreference: this.contactPreference,
            addressLine1: this.addressLine1,
            addressLine2: this.addressLine2,
            city: this.city,
            state: this.state,
            postalCode: this.postalCode,
            customerType: this.customerType,
        });
        syncEstimateVersion(payload, this.estimateVersionField);
    },
    async save() {
        const nextSnapshot = snapshot({
            firstName: this.firstName,
            lastName: this.lastName,
            phone: this.phone,
            email: this.email,
            contactPreference: this.contactPreference,
            addressLine1: this.addressLine1,
            addressLine2: this.addressLine2,
            city: this.city,
            state: this.state,
            postalCode: this.postalCode,
            customerType: this.customerType,
        });

        if (nextSnapshot === this.savedSnapshot) {
            this.editing = false;

            return;
        }

        this.saving = true;
        this.error = null;

        try {
            const payload = await patchIdentity(this, {
                first_name: this.firstName.trim(),
                last_name: this.lastName.trim(),
                phone: this.phone.trim() || '',
                email: this.email.trim() || '',
                contact_preference: this.contactPreference || '',
                address_line_1: this.addressLine1.trim() || '',
                address_line_2: this.addressLine2.trim() || '',
                city: this.city.trim() || '',
                state: this.state.trim() || '',
                postal_code: this.postalCode.trim() || '',
                customer_type: this.customerType,
                notes: this.notes,
            });

            this.applyPayload(payload);
            this.editing = false;
        } catch (error) {
            this.error = error?.message ?? 'Could not save customer.';
        } finally {
            this.saving = false;
        }
    },
});

export const arkRepairOrderIdentityVehicle = (config) => ({
    editing: false,
    saving: false,
    error: null,
    title: config.title,
    subtitle: config.subtitle ?? null,
    lines: config.lines,
    year: config.year ?? '',
    make: config.make ?? '',
    model: config.model ?? '',
    vin: config.vin ?? '',
    plate: config.plate ?? '',
    plateState: config.plateState ?? '',
    trim: config.trim ?? '',
    engine: config.engine ?? '',
    transmission: config.transmission ?? '',
    drive: config.drive ?? '',
    color: config.color ?? '',
    nickname: config.nickname ?? '',
    publicNotes: config.publicNotes ?? '',
    privateNotes: config.privateNotes ?? '',
    savedSnapshot: snapshot({
        year: config.year ?? '',
        make: config.make ?? '',
        model: config.model ?? '',
        vin: config.vin ?? '',
        plate: config.plate ?? '',
        plateState: config.plateState ?? '',
        color: config.color ?? '',
        nickname: config.nickname ?? '',
    }),
    url: config.url,
    csrf: config.csrf,
    repairOrderId: config.repairOrderId,
    estimateVersionField: config.estimateVersionField,
    mileageLineLabels: ['Mileage in', 'Mileage out', 'Mileage'],
    hideMileageLines: config.hideMileageLines ?? true,
    serviceLaneLayout: config.serviceLaneLayout ?? false,
    scanMileage: config.scanMileage ?? null,
    scanPlate: config.scanPlate ?? null,
    scanMeta() {
        if (! this.serviceLaneLayout) {
            return [];
        }

        const parts = [];
        const plateLine = (this.lines ?? []).find((line) => line.label === 'Plate');
        const plate = this.scanPlate ?? plateLine?.value ?? null;

        if (this.scanMileage) {
            parts.push({
                key: 'mileage',
                value: this.scanMileage,
                emphasis: true,
            });
        }

        if (plate) {
            parts.push({
                key: 'plate',
                value: plate,
            });
        }

        return parts;
    },
    displayLines() {
        return (this.lines ?? []).filter(
            (line) => ! this.hideMileageLines || ! this.mileageLineLabels.includes(line.label),
        );
    },
    open() {
        this.editing = true;
        this.error = null;
    },
    cancel() {
        const parsed = JSON.parse(this.savedSnapshot);
        this.year = parsed.year;
        this.make = parsed.make;
        this.model = parsed.model;
        this.vin = parsed.vin;
        this.plate = parsed.plate;
        this.plateState = parsed.plateState;
        this.color = parsed.color;
        this.nickname = parsed.nickname;
        this.editing = false;
        this.error = null;
    },
    applyPayload(payload) {
        if (! payload?.vehicle) {
            return;
        }

        this.title = payload.vehicle.title;
        this.subtitle = payload.vehicle.subtitle ?? null;
        this.lines = payload.vehicle.lines ?? [];
        this.savedSnapshot = snapshot({
            year: this.year,
            make: this.make,
            model: this.model,
            vin: this.vin,
            plate: this.plate,
            plateState: this.plateState,
            color: this.color,
            nickname: this.nickname,
        });
        syncEstimateVersion(payload, this.estimateVersionField);
    },
    async save() {
        const nextSnapshot = snapshot({
            year: this.year,
            make: this.make,
            model: this.model,
            vin: this.vin,
            plate: this.plate,
            plateState: this.plateState,
            color: this.color,
            nickname: this.nickname,
        });

        if (nextSnapshot === this.savedSnapshot) {
            this.editing = false;

            return;
        }

        this.saving = true;
        this.error = null;

        const year = String(this.year ?? '').trim();

        try {
            const payload = await patchIdentity(this, {
                year: year === '' ? '' : year,
                make: this.make.trim(),
                model: this.model.trim(),
                vin: this.vin.trim(),
                plate: this.plate.trim(),
                plate_state: this.plateState.trim(),
                trim: this.trim,
                engine: this.engine,
                transmission: this.transmission,
                drive: this.drive,
                color: this.color.trim(),
                nickname: this.nickname.trim(),
                public_notes: this.publicNotes,
                private_notes: this.privateNotes,
            });

            this.applyPayload(payload);
            this.editing = false;
        } catch (error) {
            this.error = error?.message ?? 'Could not save vehicle.';
        } finally {
            this.saving = false;
        }
    },
});

export const arkRepairOrderVehicleChange = (config) => ({
    open: false,
    saving: false,
    error: null,
    url: config.url,
    csrf: config.csrf,
    repairOrderId: config.repairOrderId,
    estimateVersionField: config.estimateVersionField,
    async changeVehicle(vehicleId) {
        if (this.saving) {
            return;
        }

        this.saving = true;
        this.error = null;

        try {
            const body = new FormData();
            body.append('_token', this.csrf);
            body.append('_method', 'PATCH');
            body.append('repair_order_id', String(this.repairOrderId));
            body.append('vehicle_id', String(vehicleId));

            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body,
            });

            const contentType = response.headers.get('content-type') ?? '';
            const isJson = contentType.includes('application/json');

            if (response.status === 422 && isJson) {
                const payload = await response.json();
                const firstError = Object.values(payload?.errors ?? {})[0]?.[0];

                throw new Error(firstError ?? 'Invalid vehicle.');
            }

            if (! response.ok || ! isJson) {
                throw new Error('Could not update vehicle. Refresh and try again.');
            }

            const payload = await response.json();
            syncEstimateVersion(payload, this.estimateVersionField);

            if (payload?.reload) {
                window.location.reload();

                return;
            }

            this.open = false;
        } catch (error) {
            this.error = error?.message ?? 'Could not update vehicle.';
        } finally {
            this.saving = false;
        }
    },
});
