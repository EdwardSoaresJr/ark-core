function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function arkDismissCompanionSuggestion(config = {}) {
    return {
        url: config.url ?? '',
        busy: false,

        async dismiss() {
            if (this.busy || ! this.url) {
                return;
            }

            this.busy = true;

            try {
                const response = await fetch(this.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                });

                if (! response.ok) {
                    return;
                }

                let el = this.$el;

                while (el) {
                    const data = window.Alpine?.$data?.(el);

                    if (data && typeof data.refreshScope === 'function') {
                        await data.refreshScope('rail', { quiet: true });
                        return;
                    }

                    el = el.parentElement;
                }

                this.$el.remove();
            } finally {
                this.busy = false;
            }
        },
    };
}
