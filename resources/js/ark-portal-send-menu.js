import { portalSendBusyLabel, withWorksheetBusy } from './ark-worksheet-busy';
import { deliveryChannelBlockReason, deliveryHttpErrorMessage, deliveryPayload } from './ark-delivery-errors';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function arkPortalSendMenu(config = {}) {
    return {
        sendUrl: config.sendUrl ?? '',
        customerEmail: config.customerEmail ?? '',
        canSms: config.canSms ?? false,
        canEmail: config.canEmail ?? false,
        smsBlockReason: config.smsBlockReason ?? '',
        emailBlockReason: config.emailBlockReason ?? '',
        sendBlockReason: config.sendBlockReason ?? '',
        missingVin: config.missingVin ?? false,
        vinBlockMessage: config.vinBlockMessage ?? 'Add the vehicle VIN before sending the estimate.',
        timingFluidsMissing: config.timingFluidsMissing ?? false,
        timingFluidsMessage: config.timingFluidsMessage ?? 'This job is missing companions the shop usually includes',
        timingFluidsDetail: config.timingFluidsDetail ?? 'Add the usual companions on this job before the customer sees the estimate.',
        addVinUrl: config.addVinUrl ?? '#ro-identity-band',
        menuOpen: false,
        menuStyle: '',
        sending: false,
        error: '',
        success: '',
        vinWarningOpen: false,
        vinAcknowledged: false,
        fluidsWarningOpen: false,
        fluidsAcknowledged: false,
        pendingDelivery: null,

        init() {
            const releaseListeners = () => {
                document.removeEventListener('click', onDocumentClick);
                window.removeEventListener('scroll', onScroll, true);
            };

            const onDocumentClick = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.menuOpen) {
                    return;
                }

                const roots = [
                    this.$refs.portalMenu,
                    this.$refs.portalMenuPanel,
                ].filter(Boolean);

                for (const root of roots) {
                    if (root.contains(event.target)) {
                        return;
                    }
                }

                this.menuOpen = false;
            };

            const onScroll = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.menuOpen) {
                    return;
                }

                const panel = this.$refs.menuPanel;

                if (panel && (panel === event.target || panel.contains(event.target))) {
                    return;
                }

                this.menuOpen = false;
            };

            document.addEventListener('click', onDocumentClick);
            window.addEventListener('scroll', onScroll, true);
        },

        syncMenuPosition() {
            this.$nextTick(() => {
                const trigger = this.$refs.portalMenuTrigger;

                if (! trigger) {
                    return;
                }

                const rect = trigger.getBoundingClientRect();
                const top = Math.round(rect.bottom + 4);
                const left = Math.round(rect.left);
                const minWidth = Math.max(Math.round(rect.width), 112);

                this.menuStyle = `top:${top}px;left:${left}px;min-width:${minWidth}px;`;
            });
        },

        toggleMenu() {
            if (this.sending) {
                return;
            }

            if (this.sendBlockReason !== '') {
                this.error = this.sendBlockReason;

                return;
            }

            if (this.sendUrl === '') {
                this.error = 'Send is unavailable on this screen. Refresh the page and try again.';

                return;
            }

            const nextOpen = ! this.menuOpen;
            this.menuOpen = nextOpen;

            if (nextOpen) {
                this.syncMenuPosition();
            }
        },

        cancelVinWarning() {
            this.vinWarningOpen = false;
            this.fluidsWarningOpen = false;
            this.pendingDelivery = null;
        },

        continueWithoutVin() {
            this.vinAcknowledged = true;
            this.vinWarningOpen = false;

            if (this.pendingDelivery) {
                this.sendPortal(this.pendingDelivery);
            }
        },

        continueWithoutTimingFluids() {
            this.fluidsAcknowledged = true;
            this.fluidsWarningOpen = false;

            if (this.pendingDelivery) {
                this.sendPortal(this.pendingDelivery);
            }
        },

        channelBlockReason(delivery) {
            if (this.sendBlockReason !== '') {
                return this.sendBlockReason;
            }

            return deliveryChannelBlockReason(delivery, {
                canSms: this.canSms,
                canEmail: this.canEmail,
                smsBlockReason: this.smsBlockReason,
                emailBlockReason: this.emailBlockReason,
            });
        },

        async sendPortal(delivery) {
            this.success = '';

            if (this.sending) {
                return;
            }

            if (this.sendUrl === '') {
                this.error = 'Send is unavailable on this screen. Refresh the page and try again.';

                return;
            }

            const channelBlockReason = this.channelBlockReason(delivery);

            if (channelBlockReason !== null) {
                this.error = channelBlockReason;
                this.menuOpen = false;

                return;
            }

            if (this.missingVin && ! this.vinAcknowledged) {
                this.pendingDelivery = delivery;
                this.vinWarningOpen = true;
                this.menuOpen = false;
                this.error = this.vinBlockMessage;

                return;
            }

            if (this.timingFluidsMissing && ! this.fluidsAcknowledged) {
                this.pendingDelivery = delivery;
                this.fluidsWarningOpen = true;
                this.menuOpen = false;
                this.error = this.timingFluidsMessage;

                return;
            }

            this.menuOpen = false;
            this.sending = true;
            this.error = '';
            this.pendingDelivery = null;

            try {
                await withWorksheetBusy(portalSendBusyLabel(delivery), async () => {
                    const response = await fetch(this.sendUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(deliveryPayload(delivery, this.customerEmail, {
                            acknowledge_missing_vin: this.vinAcknowledged,
                            acknowledge_timing_fluids: this.fluidsAcknowledged,
                        })),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            'Portal link could not be sent.',
                        );

                        return;
                    }

                    const channels = delivery === 'both'
                        ? 'SMS and email'
                        : (delivery === 'email' ? 'Email' : 'SMS');

                    this.success = `Portal link sent via ${channels}.`;

                    if (typeof window.arkReloadRepairOrderWorkspaceTab === 'function') {
                        await window.arkReloadRepairOrderWorkspaceTab('comms');
                    }
                }, this.$el);
            } catch {
                this.error = 'Portal link could not be sent. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },
    };
}
