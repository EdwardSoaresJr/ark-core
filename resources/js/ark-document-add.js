/**
 * Add Document modal body — scan page assembly + mode chooser + attach search.
 */
export function arkDocumentAdd(config = {}) {
    return {
        mode: config.initialMode || null,
        pages: [],
        nextPageId: 1,
        attachQuery: '',
        attachable: Array.isArray(config.attachable) ? config.attachable : [],

        choose(next) {
            this.mode = next;
        },

        filteredAttachable() {
            const q = this.attachQuery.trim().toLowerCase();

            if (! q) {
                return this.attachable;
            }

            return this.attachable.filter((row) => {
                const hay = [
                    row.title || '',
                    row.type_label || '',
                    row.type || '',
                ].join(' ').toLowerCase();

                return hay.includes(q);
            });
        },

        async addFiles(fileList) {
            const files = Array.from(fileList || []);

            for (const file of files) {
                if (! file.type.startsWith('image/')) {
                    continue;
                }

                const url = URL.createObjectURL(file);
                this.pages.push({ id: this.nextPageId++, file, url });
            }
        },

        removePage(id) {
            const row = this.pages.find((page) => page.id === id);

            if (row?.url) {
                URL.revokeObjectURL(row.url);
            }

            this.pages = this.pages.filter((page) => page.id !== id);
        },

        movePage(id, dir) {
            const i = this.pages.findIndex((page) => page.id === id);

            if (i < 0) {
                return;
            }

            const j = i + dir;

            if (j < 0 || j >= this.pages.length) {
                return;
            }

            const copy = [...this.pages];
            const tmp = copy[i];
            copy[i] = copy[j];
            copy[j] = tmp;
            this.pages = copy;
        },

        resetPages() {
            this.pages.forEach((page) => {
                if (page.url) {
                    URL.revokeObjectURL(page.url);
                }
            });
            this.pages = [];
        },

        back() {
            this.resetPages();
            this.attachQuery = '';
            this.mode = null;
        },

        prepareScanSubmit(event) {
            if (this.pages.length === 0) {
                event.preventDefault();
                window.alert('Capture at least one page.');

                return false;
            }

            const form = event.target;
            const transfer = new DataTransfer();
            this.pages.forEach((page) => transfer.items.add(page.file));

            let input = form.querySelector('input[data-scan-pages]');

            if (! input) {
                input = document.createElement('input');
                input.type = 'file';
                input.name = 'pages[]';
                input.multiple = true;
                input.className = 'hidden';
                input.setAttribute('data-scan-pages', '1');
                form.appendChild(input);
            }

            input.files = transfer.files;

            return true;
        },
    };
}
