import { arkPortalSignature } from './ark-portal-signature';

function authorizationSlice(config = {}) {
    const concerns = config.concerns ?? [];
    const initial = config.initialDispositions ?? {};
    const depositEnabled = config.depositEnabled ?? false;

    const dispositions = concerns.reduce((acc, concern) => {
        acc[concern.id] = initial[concern.id] ?? 'approved';

        return acc;
    }, {});

    return {
        concerns,
        dispositions,
        depositEnabled,
        approvedCount() {
            return this.concerns.filter((concern) => this.dispositions[concern.id] === 'approved').length;
        },
        deferredCount() {
            return this.concerns.filter((concern) => this.dispositions[concern.id] === 'deferred').length;
        },
        declinedCount() {
            return this.concerns.filter((concern) => this.dispositions[concern.id] === 'declined').length;
        },
        approvedTotalCents() {
            return this.concerns
                .filter((concern) => this.dispositions[concern.id] === 'approved')
                .reduce((sum, concern) => sum + (concern.subtotalCents ?? 0), 0);
        },
        formatMoney(cents) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
            }).format((cents ?? 0) / 100);
        },
        approvedTotalLabel() {
            return this.formatMoney(this.approvedTotalCents());
        },
        submitButtonLabel() {
            const total = this.concerns.length;

            if (total === 0) {
                return 'Submit my choices';
            }

            const approved = this.approvedCount();
            const deferred = this.deferredCount();
            const declined = this.declinedCount();

            if (approved === total) {
                return depositEnabled ? 'Continue to pay deposit' : 'Approve all services';
            }

            if (approved > 0) {
                if (depositEnabled) {
                    return approved === 1
                        ? 'Continue to pay deposit'
                        : `Continue to pay deposit (${approved} approved)`;
                }

                return approved === 1
                    ? 'Approve 1 service'
                    : `Approve ${approved} services`;
            }

            if (deferred === total) {
                return 'Defer all services';
            }

            if (declined === total) {
                return 'Decline all services';
            }

            return 'Submit my choices';
        },
    };
}

export function arkPortalEstimateAuthorization(config = {}) {
    return authorizationSlice(config);
}

export function arkPortalEstimateForm(config = {}) {
    const authorization = authorizationSlice({
        ...(config.authorization ?? {}),
        depositEnabled: config.depositEnabled ?? false,
    });

    if (! config.signatureRequired) {
        return {
            ...authorization,
            validateBeforeSubmit(event) {
                return this.validateDispositionsBeforeSubmit(event);
            },
            validateDispositionsBeforeSubmit(event) {
                const missing = this.concerns.filter((concern) => {
                    const disposition = this.dispositions[concern.id];

                    return disposition !== 'approved' && disposition !== 'deferred' && disposition !== 'declined';
                });

                if (missing.length > 0) {
                    event.preventDefault();
                    window.alert('Choose Approve, Defer, or Decline for each repair before submitting.');

                    return false;
                }

                return true;
            },
        };
    }

    const signature = arkPortalSignature({ required: true });

    return {
        ...signature,
        ...authorization,
        init() {
            signature.init.call(this);
        },
        validateBeforeSubmit(event) {
            if (! this.validateDispositionsBeforeSubmit(event)) {
                return false;
            }

            return signature.validateBeforeSubmit.call(this, event);
        },
        validateDispositionsBeforeSubmit(event) {
            const missing = this.concerns.filter((concern) => {
                const disposition = this.dispositions[concern.id];

                return disposition !== 'approved' && disposition !== 'deferred' && disposition !== 'declined';
            });

            if (missing.length > 0) {
                event.preventDefault();
                window.alert('Choose Approve, Defer, or Decline for each repair before submitting.');

                return false;
            }

            return true;
        },
    };
}
