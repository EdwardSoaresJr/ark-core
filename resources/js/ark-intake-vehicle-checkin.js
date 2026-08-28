export function arkIntakeVehicleCheckin(config = {}) {
    const lookupUrl = config.lookupUrl ?? '';
    const intakeUrl = config.intakeUrl ?? '';

    return {
        lookup: config.initialLookup ?? '',
        checking: false,
        message: '',

        async submit() {
            const trimmed = this.lookup.trim();
            this.message = '';

            if (trimmed.length < 2) {
                this.message = 'Enter a VIN or license plate.';

                return;
            }

            this.checking = true;

            try {
                const url = new URL(lookupUrl, window.location.origin);
                url.searchParams.set('q', trimmed);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.message = payload.message || 'Vehicle could not be found.';

                    return;
                }

                const next = new URL(intakeUrl, window.location.origin);
                next.searchParams.set('customer_id', String(payload.customer_id));
                next.searchParams.set('vehicle_id', String(payload.vehicle_id));
                window.location.assign(next.toString());
            } catch {
                this.message = 'Check-in lookup is unavailable right now.';
            } finally {
                this.checking = false;
            }
        },
    };
}
