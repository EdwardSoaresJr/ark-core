export function arkLearnGuideModal() {
    return {
        open: false,
        loading: false,
        error: '',
        title: '',
        summary: '',
        sectionLabel: '',
        html: '',
        arkademyUrl: '',

        init() {
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-arkademy-guide]');

                if (! trigger) {
                    return;
                }

                event.preventDefault();
                this.show(trigger.getAttribute('data-arkademy-guide') ?? '');
            });
        },

        async show(token) {
            if (token === '' || ! token.includes(':')) {
                return;
            }

            const [role, slug] = token.split(':', 2);

            if (role === '' || slug === '') {
                return;
            }

            this.open = true;
            this.loading = true;
            this.error = '';
            this.title = '';
            this.summary = '';
            this.sectionLabel = '';
            this.html = '';
            this.arkademyUrl = '';
            document.body.classList.add('overflow-hidden');

            try {
                const response = await fetch(`/app/learn/preview/${encodeURIComponent(role)}/${encodeURIComponent(slug)}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    throw new Error(response.status === 404 ? 'Guide not found.' : 'Could not load guide.');
                }

                const payload = await response.json();
                this.title = payload.title ?? 'Guide';
                this.summary = payload.summary ?? '';
                this.sectionLabel = payload.section_label ?? '';
                this.html = payload.html ?? '';
                this.arkademyUrl = payload.arkademy_url ?? '';
            } catch (exception) {
                this.error = exception instanceof Error ? exception.message : 'Could not load guide.';
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.open = false;
            this.loading = false;
            this.error = '';
            this.html = '';
            document.body.classList.remove('overflow-hidden');
        },
    };
}
