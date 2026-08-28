import { arkVehicleDecode, DECODED_VEHICLE_FORM_FIELDS, vehicleFormHasIdentity } from './ark-vehicle-decode';

const FIELD_NAMES = DECODED_VEHICLE_FORM_FIELDS;

export function arkCustomerVehicleRail(config = {}) {
    return {
        ...arkVehicleDecode(config),
        mode: config.initialMode ?? 'create',
        editingVehicleId: config.initialEditingVehicleId ?? null,
        editingVehicleLabel: config.initialEditingVehicleLabel ?? '',
        storeUrl: config.storeUrl ?? '',
        vehicles: config.vehicles ?? {},
        identityReady: false,
        railOpen: config.railOpenDefault ?? false,

        get isEditing() {
            return this.mode === 'edit' && this.editingVehicleId !== null;
        },

        get formAction() {
            if (this.isEditing) {
                return this.vehicles[this.editingVehicleId]?.updateUrl ?? this.storeUrl;
            }

            return this.storeUrl;
        },

        init() {
            if (config.initialEditingVehicleId) {
                this.startEdit(config.initialEditingVehicleId);
            }

            this.$nextTick(() => this.refreshIdentityReady());
        },

        openRail() {
            this.railOpen = true;
        },

        closeRail() {
            if (! this.isEditing) {
                this.railOpen = false;
            }
        },

        refreshIdentityReady() {
            this.identityReady = vehicleFormHasIdentity(this.$refs.railForm);
        },

        guardVehicleSave(event) {
            if (this.isEditing || vehicleFormHasIdentity(this.$refs.railForm)) {
                return;
            }

            event.preventDefault();
            this.message = 'Enter a VIN, plate, or year, make, and model before saving.';
        },

        startEdit(vehicleId) {
            const vehicle = this.vehicles[vehicleId];

            if (!vehicle) {
                return;
            }

            this.mode = 'edit';
            this.editingVehicleId = vehicleId;
            this.editingVehicleLabel = vehicle.label ?? '';
            this.railOpen = true;
            this.message = '';
            this.applyFields(vehicle.fields ?? {});

            this.$nextTick(() => {
                this.refreshIdentityReady();
                this.$refs.railForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },

        startCreate() {
            this.mode = 'create';
            this.editingVehicleId = null;
            this.editingVehicleLabel = '';
            this.railOpen = true;
            this.message = '';
            this.applyFields(config.createFields ?? {});
            this.$nextTick(() => this.refreshIdentityReady());
        },

        applyFields(values) {
            const form = this.$refs.railForm;

            if (!form) {
                return;
            }

            FIELD_NAMES.forEach((field) => {
                const input = form.querySelector(`[name="${field}"]`);

                if (input) {
                    input.value = values[field] ?? '';
                }
            });
        },
    };
}
