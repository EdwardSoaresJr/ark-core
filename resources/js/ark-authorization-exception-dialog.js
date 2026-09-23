export function arkAuthorizationExceptionDialog(config = {}) {
    const concernId = Number(config.concernId);

    return {
        open: config.reopen === true && config.canRecord === true,
        mode: config.canRecord === false ? 'inspect' : 'record',
        lineId: null,
        invokeEl: null,
        submitting: false,
        lineError: false,

        init() {
            this._onOpen = (event) => {
                const detail = event.detail ?? {};

                if (Number(detail.concernId) !== concernId || this.submitting) {
                    return;
                }

                const mode = detail.mode === 'inspect' ? 'inspect' : 'record';

                if (mode === 'record' && config.canRecord !== true) {
                    return;
                }

                this.mode = mode;
                this.lineId = detail.lineId ? Number(detail.lineId) : null;
                this.invokeEl = detail.invokeEl instanceof HTMLElement ? detail.invokeEl : null;
                this.openDialog();
            };

            window.addEventListener('ark-authorization-exception-open', this._onOpen);

            if (this.open) {
                this.openDialog();
            }
        },

        destroy() {
            window.removeEventListener('ark-authorization-exception-open', this._onOpen);

            if (this.open) {
                document.body.classList.remove('overflow-y-hidden');
            }
        },

        showsException(lineIds) {
            if (this.mode !== 'inspect') {
                return false;
            }

            if (! this.lineId) {
                return true;
            }

            return (Array.isArray(lineIds) ? lineIds : [])
                .map((id) => Number(id))
                .includes(Number(this.lineId));
        },

        openDialog() {
            this.open = true;
            document.body.classList.add('overflow-y-hidden');
            this.$nextTick(() => this.focusFirst());
        },

        onEscape(event) {
            if (! this.open) {
                return;
            }

            event.stopPropagation();
            this.close();
        },

        submitRecord(event) {
            const form = event.target;

            if (! (form instanceof HTMLFormElement)) {
                return;
            }

            const selected = form.querySelectorAll('input[name="line_ids[]"]:checked');

            if (selected.length === 0) {
                event.preventDefault();
                this.lineError = true;
                this.submitting = false;

                return;
            }

            this.lineError = false;
            this.submitting = true;
        },

        close() {
            if (! this.open || this.submitting) {
                return;
            }

            this.open = false;
            document.body.classList.remove('overflow-y-hidden');

            const invokeEl = this.invokeEl;
            this.invokeEl = null;

            this.$nextTick(() => {
                if (invokeEl instanceof HTMLElement && document.contains(invokeEl)) {
                    invokeEl.focus();
                }
            });
        },

        focusFirst() {
            const dialog = this.$refs.dialog;

            if (! (dialog instanceof HTMLElement)) {
                return;
            }

            if (this.mode === 'record') {
                const invalid = dialog.querySelector('[aria-invalid="true"]');
                const field = invalid instanceof HTMLElement
                    ? invalid
                    : dialog.querySelector('select, textarea');

                if (field instanceof HTMLElement) {
                    field.focus();

                    return;
                }
            }

            dialog.focus();
        },

        focusables() {
            const dialog = this.$refs.dialog;

            if (! (dialog instanceof HTMLElement)) {
                return [];
            }

            const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            return [...dialog.querySelectorAll(selector)].filter((el) => el.offsetParent !== null);
        },

        trapFocus(event) {
            if (! this.open || event.key !== 'Tab') {
                return;
            }

            const items = this.focusables();

            if (items.length === 0) {
                event.preventDefault();
                this.$refs.dialog?.focus();

                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    };
}
