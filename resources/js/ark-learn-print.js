export function arkLearnPrintSelect() {
    return {
        selected: [],

        get selectedCount() {
            return this.selected.length;
        },

        toggleSection(sectionKey, checked) {
            const inputs = this.root().querySelectorAll(`input[data-section="${sectionKey}"][name="pick[]"]`);

            inputs.forEach((input) => {
                const value = input.value;

                if (checked) {
                    if (! this.selected.includes(value)) {
                        this.selected.push(value);
                    }
                } else {
                    this.selected = this.selected.filter((pick) => pick !== value);
                }
            });
        },

        syncSectionToggle(sectionKey) {
            const inputs = Array.from(this.root().querySelectorAll(`input[data-section="${sectionKey}"][name="pick[]"]`));
            const toggle = this.root().querySelector(`input[data-section-toggle="${sectionKey}"]`);

            if (! toggle || inputs.length === 0) {
                return;
            }

            toggle.indeterminate = this.selected.some((pick) => inputs.some((input) => input.value === pick))
                && ! inputs.every((input) => this.selected.includes(input.value));

            toggle.checked = inputs.every((input) => this.selected.includes(input.value));
        },

        openPrintPreview() {
            if (this.selected.length === 0) {
                return;
            }

            const url = new URL(this.root().dataset.printUrl, window.location.origin);

            this.selected.forEach((pick) => url.searchParams.append('pick[]', pick));

            window.open(url.toString(), '_blank', 'noopener');
        },

        root() {
            return this.$el;
        },
    };
}
